import { expect } from "chai";
import * as fs from "fs";
import sinon from "sinon";

global["dojo"] = { clone: (x: any) => JSON.parse(JSON.stringify(x ?? null)) };

/**
 * Replays the order of calls BGA framework makes around private states (as read from ly_studio.js,
 * ntf_gameStateChange and ntf_gameStateMultipleActiveUpdate) to check onUpdateActionButtons is not stuck waiting
 * for onEnteringState, bug https://boardgamearena.com/bug?id=184677 (Undo missing after draft pick)
 */
describe("GameBasics private states", () => {
  var GameBasics;
  let sandbox: sinon.SinonSandbox;
  let game: any;
  let stateClasses: { [name: string]: any };

  const gamestates = {
    10: { name: "gameDispatch", type: "game" },
    11: { name: "playerTurnChoice", type: "activeplayer" },
    12: { name: "multiplayerDispatch", type: "multipleactiveplayer" },
    13: { name: "multiplayerChoice", type: "private" }
  };
  const publicState = () => ({ ...gamestates[12], args: { ooturn: {} } });
  const privateState = () => ({ ...gamestates[13], args: { player_operations: {} } });

  before(() => {
    let xfile = fs.readFileSync("terraformingmars.js", "utf8");
    expect(xfile).to.be.not.equal("");
    xfile += "\nmodule.exports.GameBasics = GameBasics;function define(a,b) {};\n";
    fs.writeFileSync("tests/out_basics.jsa", xfile);
    var mod = require("./out_basics.jsa");
    GameBasics = mod.GameBasics;
  });

  beforeEach(() => {
    sandbox = sinon.createSandbox();
    sandbox.stub(console, "log");
    game = new GameBasics();
    for (const method of ["setupInfoPanel", "setupNotifications", "upldateColorMapping", "restoreMainBar", "disconnectAllTemp", "removeAllClasses"]) {
      game[method] = sandbox.stub();
    }
    game.isCurrentPlayerActive = sandbox.stub().returns(true);
    sandbox.spy(game, "onLeavingState");
    sandbox.spy(game, "onUpdateActionButtons_after");

    stateClasses = {};
    game.bga = { states: { register: (name: string, cls: any) => (stateClasses[name] = cls) } };
    game.gamedatas = { gamestates, gamestate: publicState() };
    game.setup(game.gamedatas);

    // player is in the draft: entered multiplayerDispatch then its private state
    game.onEnteringState("multiplayerDispatch", game.gamedatas.gamestate);
    game.gamedatas.gamestate.private_state = privateState();
    game.onEnteringState("multiplayerChoice", game.gamedatas.gamestate.private_state);
    game.onUpdateActionButtons_after.resetHistory();
  });

  afterEach(() => {
    sandbox.restore();
  });

  it("registers a state class only for private states", () => {
    expect(Object.keys(stateClasses)).to.deep.equal(["multiplayerChoice"]);
  });

  it("updates action buttons when player is deactivated out of private state", () => {
    // ntf_gameStateMultipleActiveUpdate: drops private state, calls only bga.states.onLeavingState, then updatePageTitle
    game.isCurrentPlayerActive.returns(false);
    game.gamedatas.gamestate.private_state = null;
    stateClasses["multiplayerChoice"].onLeavingState(privateState().args, false);
    game.onUpdateActionButtons("multiplayerDispatch", game.gamedatas.gamestate.args);

    expect(game.onLeavingState.calledOnceWith("multiplayerChoice")).to.be.true;
    expect(game.onUpdateActionButtons_after.calledOnceWith("multiplayerDispatch")).to.be.true;
    expect(game.pendingUpdate).to.be.false;
  });

  it("still defers action buttons until onEnteringState on normal state change", () => {
    // ntf_gameStateChange: game and bga.states onLeavingState for private then public state while private_state is set
    game.onLeavingState("multiplayerChoice");
    stateClasses["multiplayerChoice"].onLeavingState(privateState().args, true);
    game.onLeavingState("multiplayerDispatch");
    const next = { ...gamestates[11], args: { operations: {} } };
    game.gamedatas.gamestate = next;
    game.onUpdateActionButtons("playerTurnChoice", next.args);

    expect(game.onLeavingState.withArgs("multiplayerChoice").calledOnce).to.be.true;
    expect(game.onUpdateActionButtons_after.called).to.be.false;
    expect(game.pendingUpdate).to.be.true;

    game.onEnteringState("playerTurnChoice", next);
    expect(game.onUpdateActionButtons_after.calledOnceWith("playerTurnChoice")).to.be.true;
  });
});
