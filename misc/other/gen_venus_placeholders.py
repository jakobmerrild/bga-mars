"""
Generates stand-in art for the Venus Next corporations (no scans yet):

  img/venus/venus_corps.jpg       card faces, 5 slots of 844x600 (card_corp_14..18)
  img/venus/venus_corps_logo.png  player board logos, 5 slots of 680x200

The card face only draws the frame, name, tags and starting M€; the rules text is overlaid by the
client (.card_initial / .card_effect), so the lower half is left empty for it.

Usage (needs Pillow and the Windows fonts): python misc/other/gen_venus_placeholders.py
"""

import os

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
OUT = os.path.join(ROOT, "img", "venus")
FONTS = "C:/Windows/Fonts/"

# num, name, tags, starting M€, label of the rules box
CORPS = [
    (14, "Aphrodite", ["Plant", "Venus"], 47, "EFFECT"),
    (15, "Celestic", ["Venus"], 42, "ACTION"),
    (16, "Manutech", ["Building"], 35, "EFFECT"),
    (17, "Morning Star Inc.", ["Venus"], 50, "EFFECT"),
    (18, "Viron", ["Microbe"], 48, "ACTION"),
]

TAGS = {
    # fill, rim, glyph, glyph font
    "Venus": ((236, 226, 196), (120, 110, 160), "\u2640", "seguisym.ttf"),
    "Plant": ((92, 168, 64), (30, 80, 30), "\u2663", "seguisym.ttf"),
    "Building": ((150, 100, 60), (70, 40, 20), "\u25a6", "seguisym.ttf"),
    "Microbe": ((140, 200, 90), (40, 90, 30), "\u273a", "seguisym.ttf"),
}

CW, CH = 844, 600  # card slot, same aspect as .card.corp (1.4066)
LW, LH = 680, 200  # logo slot, same aspect as .corp_logo (1 / 0.294)


def font(name, size):
    return ImageFont.truetype(FONTS + name, size)


def fit_font(draw, text, name, size, max_w):
    while size > 10:
        f = font(name, size)
        if draw.textlength(text, font=f) <= max_w:
            return f
        size -= 2
    return font(name, size)


def vgradient(w, h, top, bottom):
    im = Image.new("RGB", (w, h))
    d = ImageDraw.Draw(im)
    for y in range(h):
        t = y / max(1, h - 1)
        d.line([(0, y), (w, y)], fill=tuple(int(a + (b - a) * t) for a, b in zip(top, bottom)))
    return im


def venus_disc(size):
    """A hazy, banded Venus-like planet as an RGBA image."""
    im = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    disc = vgradient(size, size, (255, 240, 200), (226, 170, 96))
    bands = ImageDraw.Draw(disc, "RGBA")
    for i, y in enumerate(range(size // 7, size, size // 6)):
        bands.ellipse([-size // 2, y - size // 14, size * 3 // 2, y + size // 14],
                      fill=(255, 248, 225, 60) if i % 2 == 0 else (215, 160, 90, 45))
    disc = disc.filter(ImageFilter.GaussianBlur(size / 40)).convert("RGBA")
    mask = Image.new("L", (size, size), 0)
    ImageDraw.Draw(mask).ellipse([0, 0, size - 1, size - 1], fill=255)
    im.paste(disc, (0, 0), mask)
    # terminator shadow
    shade = Image.new("L", (size, size), 0)
    ImageDraw.Draw(shade).ellipse([size * 0.25, size * 0.2, size * 1.4, size * 1.3], fill=90)
    shade = shade.filter(ImageFilter.GaussianBlur(size / 10))
    shade = Image.composite(shade, Image.new("L", (size, size), 0), mask)
    im.paste((150, 85, 30, 255), (0, 0), shade)
    return im


def outlined_text(draw, xy, text, f, fill, outline, width, anchor="la"):
    draw.text(xy, text, font=f, fill=fill, anchor=anchor, stroke_width=width, stroke_fill=outline)


def draw_tag(img, cx, cy, r, tag):
    fill, rim, glyph, gfont = TAGS[tag]
    d = ImageDraw.Draw(img)
    d.ellipse([cx - r - 3, cy - r - 3, cx + r + 3, cy + r + 3], fill=(40, 40, 40))
    d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=fill, outline=rim, width=max(2, r // 8))
    d.text((cx, cy + r * 0.05), glyph, font=font(gfont, int(r * 1.3)), fill=rim, anchor="mm")


def draw_megacredit(img, x, y, s, amount):
    """Yellow rounded M€ token with the amount, like the printed cards."""
    d = ImageDraw.Draw(img)
    d.rounded_rectangle([x + 3, y + 4, x + s + 3, y + s + 4], radius=s // 5, fill=(70, 50, 0))
    d.rounded_rectangle([x, y, x + s, y + s], radius=s // 5, fill=(250, 200, 30), outline=(150, 100, 0), width=4)
    d.rounded_rectangle([x + s * 0.14, y + s * 0.14, x + s * 0.86, y + s * 0.86], radius=s // 8,
                        outline=(200, 140, 10), width=3)
    d.text((x + s / 2, y + s / 2), str(amount), font=font("arialbd.ttf", int(s * 0.5)), fill=(40, 30, 0),
           anchor="mm")


def make_card(num, name, tags, mc, box_label):
    card = Image.new("RGB", (CW, CH), (255, 255, 255))
    pad = 14
    # frame
    frame = ImageDraw.Draw(card)
    frame.rounded_rectangle([pad, pad, CW - pad, CH - pad], radius=48, fill=(118, 120, 126))
    inner = vgradient(CW - 2 * pad - 20, CH - 2 * pad - 20, (246, 228, 196), (252, 249, 242))
    # warm haze of the Venus clouds in the upper part
    haze = Image.new("RGBA", inner.size, (0, 0, 0, 0))
    hd = ImageDraw.Draw(haze)
    for i, (cx, cy, r, a) in enumerate([(0.75, 0.15, 0.55, 90), (0.2, 0.05, 0.4, 70), (0.55, 0.45, 0.35, 40)]):
        w, h = inner.size
        hd.ellipse([w * cx - w * r, h * cy - h * r, w * cx + w * r, h * cy + h * r], fill=(232, 176, 92, a))
    haze = haze.filter(ImageFilter.GaussianBlur(40))
    inner = inner.convert("RGBA")
    inner.alpha_composite(haze)
    mask = Image.new("L", inner.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, inner.size[0] - 1, inner.size[1] - 1], radius=36, fill=255)
    card.paste(inner.convert("RGB"), (pad + 10, pad + 10), mask)

    # planet peeking in from the top right, behind everything else
    disc = venus_disc(520)
    planet = Image.new("RGBA", (CW, CH), (0, 0, 0, 0))
    planet.alpha_composite(disc, (CW - 330, -260))
    alpha = planet.getchannel("A").point(lambda a: a * 55 // 100)
    planet.putalpha(Image.composite(alpha, Image.new("L", (CW, CH), 0), _inner_mask(pad)))
    card = card.convert("RGBA")
    card.alpha_composite(planet)

    d = ImageDraw.Draw(card)
    # "CORPORATION" banner
    bw, bh = 300, 34
    bx = (CW - bw) // 2
    d.polygon([(bx - 16, pad), (bx + bw + 16, pad), (bx + bw, pad + bh), (bx, pad + bh)], fill=(240, 150, 30),
              outline=(150, 80, 0))
    d.text((CW / 2, pad + bh / 2), "CORPORATION", font=font("arialbd.ttf", 22), fill=(90, 40, 0), anchor="mm")

    # tags, top right
    r = 40
    for i, tag in enumerate(reversed(tags)):
        draw_tag(card, CW - 82 - i * (2 * r + 14), 92, r, tag)

    # name plate
    tag_room = len(tags) * (2 * r + 14) + 70
    max_w = CW - 70 - tag_room
    nf = fit_font(d, name.upper(), "impact.ttf", 92, max_w)
    outlined_text(d, (58, 140), name.upper(), nf, (255, 236, 170), (90, 45, 10), 5, anchor="ls")
    d.text((60, 172), "VENUS NEXT  \u2022  PLACEHOLDER ART", font=font("arialbd.ttf", 20), fill=(150, 100, 60),
           anchor="ls")

    # starting M€, left; the client puts the starting text below it (top 66%)
    s = 112
    draw_megacredit(card, int(CW * 0.235 - s / 2), int(CH * 0.43), s, mc)

    # rules box, right; the client puts the effect / action text in it (top 60%)
    bx0, by0, bx1, by1 = int(CW * 0.535), int(CH * 0.47), int(CW * 0.955), int(CH * 0.93)
    box = vgradient(bx1 - bx0, by1 - by0, (226, 228, 232), (168, 172, 180))
    bm = Image.new("L", box.size, 0)
    ImageDraw.Draw(bm).rounded_rectangle([0, 0, box.size[0] - 1, box.size[1] - 1], radius=10, fill=230)
    card.paste(box, (bx0, by0), bm)
    d.rounded_rectangle([bx0, by0, bx1, by1], radius=10, outline=(110, 112, 120), width=3)
    lw = 180
    lx = (bx0 + bx1) // 2 - lw // 2
    d.rounded_rectangle([lx, by0 + 10, lx + lw, by0 + 40], radius=6, fill=(40, 110, 200), outline=(20, 60, 130),
                        width=2)
    d.text(((bx0 + bx1) / 2, by0 + 25), box_label, font=font("arialbd.ttf", 21), fill=(255, 255, 255),
           anchor="mm")
    return card.convert("RGB")


def _inner_mask(pad):
    m = Image.new("L", (CW, CH), 0)
    ImageDraw.Draw(m).rounded_rectangle([pad + 10, pad + 10, CW - pad - 10, CH - pad - 10], radius=36, fill=255)
    return m


def make_logo(name):
    logo = Image.new("RGBA", (LW, LH), (0, 0, 0, 0))
    disc = venus_disc(LH - 24)
    logo.alpha_composite(disc, (8, 12))
    d = ImageDraw.Draw(logo)
    d.ellipse([8, 12, 8 + LH - 25, 12 + LH - 25], outline=(90, 45, 10), width=4)
    x0 = LH + 6
    nf = fit_font(d, name.upper(), "impact.ttf", 110, LW - x0 - 12)
    outlined_text(d, (x0, LH / 2 + 4), name.upper(), nf, (255, 226, 140), (70, 35, 5), 6, anchor="lm")
    return logo


def main():
    os.makedirs(OUT, exist_ok=True)
    cards = Image.new("RGB", (CW * len(CORPS), CH), (255, 255, 255))
    logos = Image.new("RGBA", (LW * len(CORPS), LH), (0, 0, 0, 0))
    for i, (num, name, tags, mc, label) in enumerate(CORPS):
        cards.paste(make_card(num, name, tags, mc, label), (i * CW, 0))
        logos.alpha_composite(make_logo(name), (i * LW, 0))
    cards.save(os.path.join(OUT, "venus_corps.jpg"), quality=85, optimize=True)
    logos.save(os.path.join(OUT, "venus_corps_logo.png"), optimize=True)


if __name__ == "__main__":
    main()
