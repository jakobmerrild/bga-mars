"""
Generates stand-in art for the Venus Next corporations (no scans yet), loosely modelled on the printed
cards: silver frame, orange CORPORATION banner, tag bracket, corp logo, starting resources and an
effect / action box with its icons.

  img/venus/venus_corps.jpg       card faces, 5 slots of 844x600 (card_corp_14..18)
  img/venus/venus_corps_logo.png  player board logos, 5 slots of 680x200

The rules text is overlaid by the client (.card_initial at left 6% / top 66%, .card_effect at
left 56% / top 60%), so those areas are kept light and free of icons.

Everything is drawn at 2x (S) and downscaled for anti-aliasing; coordinates below are in 2x pixels.

Usage (needs Pillow and the Windows fonts): python misc/other/gen_venus_placeholders.py
"""

import math
import os
import random

from PIL import Image, ImageChops, ImageDraw, ImageFilter, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
OUT = os.path.join(ROOT, "img", "venus")
FONTS = "C:/Windows/Fonts/"

S = 2
CW, CH = 844, 600  # card slot, same aspect as .card.corp (1.4066)
LW, LH = 680, 200  # logo slot, same aspect as .corp_logo (1 / 0.294)
W, H = CW * S, CH * S

# frame geometry
PAD = 24  # white card edge
FRAME = 30  # silver frame thickness
IN0, IN1 = PAD + FRAME, W - PAD - FRAME  # inner area x (and y uses H)


# ---------------------------------------------------------------------------------------------------------
# helpers


def font(name, size):
    return ImageFont.truetype(FONTS + name, int(size))


def gradient(w, h, stops, vertical=True):
    """Linear gradient through [(t, (r, g, b)), ...] stops, t in 0..1."""
    n = h if vertical else w
    line = Image.new("RGB", (1, n) if vertical else (n, 1))
    px = line.load()
    for i in range(n):
        t = i / max(1, n - 1)
        for (t0, c0), (t1, c1) in zip(stops, stops[1:]):
            if t0 <= t <= t1:
                u = (t - t0) / max(1e-6, t1 - t0)
                c = tuple(int(a + (b - a) * u) for a, b in zip(c0, c1))
                break
        px[(0, i) if vertical else (i, 0)] = c
    return line.resize((w, h))


def radial(size, inner, outer, center=(0.5, 0.5), power=1.0):
    im = Image.radial_gradient("L").resize((size * 2, size * 2))
    cx, cy = center
    im = im.crop((int(size * (1 - cx)), int(size * (1 - cy)), int(size * (2 - cx)), int(size * (2 - cy))))
    if power != 1.0:
        im = im.point(lambda v: int(255 * (v / 255) ** power))
    return Image.composite(Image.new("RGB", (size, size), outer), Image.new("RGB", (size, size), inner), im)


def ellipse_mask(w, h, box=None):
    m = Image.new("L", (w, h), 0)
    ImageDraw.Draw(m).ellipse(box or [0, 0, w - 1, h - 1], fill=255)
    return m


def paste_fill(canvas, fill_img, mask, xy=(0, 0)):
    """Composite an RGB fill through an L mask onto an RGBA canvas."""
    layer = fill_img.convert("RGB").crop((0, 0) + mask.size)
    layer.putalpha(mask)
    canvas.alpha_composite(layer, xy)


def text_mask(size, xy, text, f, anchor="la", stroke=0, spacing=0):
    m = Image.new("L", size, 0)
    d = ImageDraw.Draw(m)
    if spacing:
        x, y = xy
        total = sum(d.textlength(ch, font=f) for ch in text) + spacing * (len(text) - 1)
        if anchor[0] == "m":
            x -= total / 2
        for ch in text:
            d.text((x, y), ch, font=f, fill=255, anchor="l" + anchor[1], stroke_width=stroke, stroke_fill=255)
            x += d.textlength(ch, font=f) + spacing
    else:
        d.text(xy, text, font=f, fill=255, anchor=anchor, stroke_width=stroke, stroke_fill=255)
    return m


def fancy_text(canvas, xy, text, f, stops, outline=None, ow=0, anchor="la", shadow=0, spacing=0):
    """Text filled with a vertical gradient, optional outline and drop shadow."""
    size = canvas.size
    body = text_mask(size, xy, text, f, anchor, 0, spacing)
    bbox = body.getbbox()
    if not bbox:
        return
    if shadow:
        sm = text_mask(size, xy, text, f, anchor, ow, spacing).filter(ImageFilter.GaussianBlur(shadow))
        sm = ImageChops.offset(sm, shadow, shadow).point(lambda v: v * 6 // 10)
        paste_fill(canvas, Image.new("RGB", size, (30, 20, 10)), sm)
    if outline and ow:
        paste_fill(canvas, Image.new("RGB", size, outline), text_mask(size, xy, text, f, anchor, ow, spacing))
    g = Image.new("RGB", size, stops[0][1])
    x0, y0, x1, y1 = bbox
    g.paste(gradient(x1 - x0, y1 - y0, stops), (x0, y0))
    paste_fill(canvas, g, body)


def fit_size(text, fname, size, max_w, spacing=0):
    d = ImageDraw.Draw(Image.new("L", (1, 1)))
    while size > 10:
        f = font(fname, size)
        if d.textlength(text, font=f) + spacing * (len(text) - 1) <= max_w:
            return f
        size -= 2
    return font(fname, size)


def octagon(x0, y0, x1, y1, c):
    return [(x0 + c, y0), (x1 - c, y0), (x1, y0 + c), (x1, y1 - c), (x1 - c, y1), (x0 + c, y1), (x0, y1 - c),
            (x0, y0 + c)]


def poly_mask(size, pts):
    m = Image.new("L", size, 0)
    ImageDraw.Draw(m).polygon(pts, fill=255)
    return m


METAL = [(0, (120, 122, 128)), (0.25, (238, 240, 244)), (0.5, (160, 163, 170)), (0.75, (226, 228, 232)),
         (1, (110, 112, 118))]
GOLD = [(0, (255, 236, 120)), (0.5, (250, 190, 30)), (1, (220, 120, 10))]


# ---------------------------------------------------------------------------------------------------------
# icons


def draw_megacredit(c, cx, cy, s, amount):
    """Yellow chamfered M€ token with the amount, like the printed cards."""
    x0, y0, x1, y1 = cx - s / 2, cy - s / 2, cx + s / 2, cy + s / 2
    sh = poly_mask(c.size, octagon(x0 + 5, y0 + 7, x1 + 5, y1 + 7, s * 0.24)).filter(ImageFilter.GaussianBlur(4))
    paste_fill(c, Image.new("RGB", c.size, (60, 40, 0)), sh.point(lambda v: v * 6 // 10))
    paste_fill(c, Image.new("RGB", c.size, (170, 90, 0)), poly_mask(c.size, octagon(x0, y0, x1, y1, s * 0.24)))
    g = Image.new("RGB", c.size)
    g.paste(gradient(int(s), int(s), GOLD), (int(x0), int(y0)))
    i = s * 0.07
    paste_fill(c, g, poly_mask(c.size, octagon(x0 + i, y0 + i, x1 - i, y1 - i, s * 0.2)))
    d = ImageDraw.Draw(c)
    d.polygon(octagon(x0 + s * 0.15, y0 + s * 0.15, x1 - s * 0.15, y1 - s * 0.15, s * 0.14), outline=(230, 150, 10),
              width=max(2, int(s * 0.025)))
    d.rectangle([cx - s * 0.12, y1 - s * 0.1, cx + s * 0.12, y1 - s * 0.04], fill=(230, 150, 10))
    fancy_text(c, (cx, cy + s * 0.02), str(amount), font("ariblk.ttf", s * (0.5 if len(str(amount)) < 2 else 0.46)),
               [(0, (40, 25, 0)), (1, (20, 10, 0))], (255, 240, 170), max(2, int(s * 0.03)), anchor="mm")


def draw_venus_tag(c, cx, cy, r):
    disc = radial(2 * r, (120, 190, 255), (20, 70, 170), center=(0.4, 0.35), power=0.8)
    paste_fill(c, disc, ellipse_mask(2 * r, 2 * r), (cx - r, cy - r))
    d = ImageDraw.Draw(c)
    d.ellipse([cx - r, cy - r, cx + r, cy + r], outline=(235, 240, 250), width=max(3, r // 9))
    fancy_text(c, (cx, cy + r * 0.06), "V", font("arialbd.ttf", r * 1.25), [(0, (255, 255, 255)), (1, (220, 230, 245))],
               (15, 45, 110), max(2, r // 16), anchor="mm")


def draw_leaf(c, cx, cy, s, col=(40, 120, 30), vein=(170, 230, 120)):
    leaf = Image.new("RGBA", (s * 2, s * 2), (0, 0, 0, 0))
    d = ImageDraw.Draw(leaf)
    d.ellipse([s * 0.55, s * 0.2, s * 1.45, s * 1.8], fill=col)
    d.line([(s, s * 0.35), (s, s * 1.95)], fill=vein, width=max(2, s // 10))
    leaf = leaf.rotate(-40, resample=Image.BICUBIC)
    c.alpha_composite(leaf, (int(cx - s), int(cy - s)))


def draw_plant_tag(c, cx, cy, r):
    paste_fill(c, radial(2 * r, (130, 210, 80), (30, 120, 30), center=(0.4, 0.35)), ellipse_mask(2 * r, 2 * r),
               (cx - r, cy - r))
    ImageDraw.Draw(c).ellipse([cx - r, cy - r, cx + r, cy + r], outline=(20, 70, 20), width=max(3, r // 10))
    draw_leaf(c, cx, cy, int(r * 0.7), (25, 90, 20), (120, 200, 90))


def draw_building_tag(c, cx, cy, r):
    paste_fill(c, radial(2 * r, (190, 120, 70), (110, 60, 30), center=(0.4, 0.35)), ellipse_mask(2 * r, 2 * r),
               (cx - r, cy - r))
    d = ImageDraw.Draw(c)
    d.ellipse([cx - r, cy - r, cx + r, cy + r], outline=(60, 30, 10), width=max(3, r // 10))
    d.polygon([(cx - r * 0.62, cy + r * 0.25), (cx - r * 0.3, cy - r * 0.3), (cx + r * 0.3, cy - r * 0.3),
               (cx + r * 0.62, cy + r * 0.25)], fill=(55, 25, 5))
    d.rectangle([cx - r * 0.62, cy + r * 0.25, cx + r * 0.62, cy + r * 0.36], fill=(55, 25, 5))


def draw_microbe_tag(c, cx, cy, r):
    paste_fill(c, radial(2 * r, (220, 235, 90), (130, 160, 20), center=(0.4, 0.35)), ellipse_mask(2 * r, 2 * r),
               (cx - r, cy - r))
    d = ImageDraw.Draw(c)
    d.ellipse([cx - r, cy - r, cx + r, cy + r], outline=(70, 90, 10), width=max(3, r // 10))
    dark = (60, 70, 10)
    # a spiky blob and a rod, like the printed microbe icon
    bx, by, br = cx - r * 0.22, cy - r * 0.05, r * 0.3
    for a in range(0, 360, 30):
        t = math.radians(a)
        d.line([(bx, by), (bx + math.cos(t) * br * 1.45, by + math.sin(t) * br * 1.45)], fill=dark, width=max(2, r // 14))
    d.ellipse([bx - br, by - br, bx + br, by + br], fill=dark)
    rod = Image.new("RGBA", (r * 2, r * 2), (0, 0, 0, 0))
    ImageDraw.Draw(rod).rounded_rectangle([r * 0.75, r * 0.55, r * 1.25, r * 1.45], radius=r // 4, fill=dark)
    c.alpha_composite(rod.rotate(-35, resample=Image.BICUBIC), (int(cx - r * 0.55), int(cy - r * 0.9)))


TAG_DRAW = {"Venus": draw_venus_tag, "Plant": draw_plant_tag, "Building": draw_building_tag,
            "Microbe": draw_microbe_tag}


def draw_tag(c, cx, cy, r, tag):
    d = ImageDraw.Draw(c)
    sh = ellipse_mask(*c.size, [cx - r - 6, cy - r - 2, cx + r + 10, cy + r + 12]).filter(ImageFilter.GaussianBlur(6))
    paste_fill(c, Image.new("RGB", c.size, (20, 20, 20)), sh.point(lambda v: v // 2))
    d.ellipse([cx - r - 9, cy - r - 9, cx + r + 9, cy + r + 9], fill=(70, 72, 78))
    ring = gradient(2 * r + 12, 2 * r + 12, METAL)
    paste_fill(c, ring, ellipse_mask(2 * r + 12, 2 * r + 12), (cx - r - 6, cy - r - 6))
    TAG_DRAW[tag](c, cx, cy, r)


def draw_tag_bracket(c, x1, cy, h):
    """The striped metal bracket the tags sit in, to the left of the first tag."""
    x0 = x1 - 46
    m = Image.new("L", c.size, 0)
    ImageDraw.Draw(m).rounded_rectangle([x0, cy - h / 2, x1 + 40, cy + h / 2], radius=14, fill=255)
    g = Image.new("RGB", c.size)
    g.paste(gradient(int(x1 + 40 - x0), int(h), METAL), (int(x0), int(cy - h / 2)))
    paste_fill(c, g, m)
    d = ImageDraw.Draw(c)
    d.rounded_rectangle([x0, cy - h / 2, x1 + 40, cy + h / 2], radius=14, outline=(70, 72, 78), width=3)
    for i, col in enumerate([(60, 60, 64), (240, 140, 20), (60, 60, 64), (240, 140, 20)]):
        x = x0 + 8 + i * 8
        d.line([(x, cy - h / 2 + 10), (x, cy + h / 2 - 10)], fill=col, width=5)


def draw_prod_box(c, cx, cy, s, inner):
    """Brown production box with a resource icon inside (inner: callable(c, cx, cy, s))."""
    x0, y0 = cx - s / 2, cy - s / 2
    d = ImageDraw.Draw(c)
    d.rectangle([x0 + 6, y0 + 8, x0 + s + 6, y0 + s + 8], fill=(60, 40, 25))
    g = gradient(int(s), int(s), [(0, (190, 120, 70)), (1, (120, 70, 35))])
    c.paste(g, (int(x0), int(y0)))
    d.rectangle([x0, y0, x0 + s, y0 + s], outline=(70, 40, 15), width=4)
    d.rectangle([x0 + s * 0.08, y0 + s * 0.08, x0 + s * 0.92, y0 + s * 0.92], outline=(220, 170, 120), width=2)
    inner(c, cx, cy, s * 0.62)


def draw_steel_res(c, cx, cy, s):
    x0, y0 = int(cx - s / 2), int(cy - s / 2)
    c.paste(gradient(int(s), int(s), [(0, (175, 120, 75)), (1, (120, 75, 40))]), (x0, y0))
    d = ImageDraw.Draw(c)
    d.rectangle([x0, y0, x0 + s, y0 + s], outline=(60, 35, 15), width=3)
    d.text((cx, cy + s * 0.04), "\u2692", font=font("seguisym.ttf", s * 0.8), fill=(45, 25, 10), anchor="mm")


def draw_plant_res(c, cx, cy, s):
    x0, y0 = int(cx - s / 2), int(cy - s / 2)
    c.paste(gradient(int(s), int(s), [(0, (110, 200, 70)), (1, (40, 130, 35))]), (x0, y0))
    ImageDraw.Draw(c).rectangle([x0, y0, x0 + s, y0 + s], outline=(20, 70, 20), width=3)
    draw_leaf(c, cx, cy, int(s * 0.42), (25, 95, 20), (130, 210, 100))


def draw_mystery_res(c, cx, cy, s):
    x0, y0 = int(cx - s / 2), int(cy - s / 2)
    c.paste(gradient(int(s), int(s), [(0, (255, 255, 255)), (1, (215, 215, 220))]), (x0, y0))
    d = ImageDraw.Draw(c)
    d.rectangle([x0, y0, x0 + s, y0 + s], outline=(40, 40, 40), width=3)
    d.text((cx, cy + s * 0.05), "?", font=font("ariblk.ttf", s * 0.8), fill=(20, 20, 20), anchor="mm")


def draw_floater(c, cx, cy, s):
    x0, y0 = cx - s / 2, cy - s / 2
    m = Image.new("L", c.size, 0)
    ImageDraw.Draw(m).rounded_rectangle([x0, y0, x0 + s, y0 + s], radius=s * 0.12, fill=255)
    g = Image.new("RGB", c.size)
    g.paste(gradient(int(s), int(s), [(0, (255, 235, 90)), (1, (240, 175, 20))]), (int(x0), int(y0)))
    paste_fill(c, g, m)
    d = ImageDraw.Draw(c)
    d.rounded_rectangle([x0, y0, x0 + s, y0 + s], radius=s * 0.12, outline=(170, 100, 0), width=max(2, int(s * 0.04)))
    # cloud
    cl = Image.new("L", c.size, 0)
    cd = ImageDraw.Draw(cl)
    for ox, oy, rr in [(-0.2, 0.05, 0.16), (0.0, -0.08, 0.22), (0.2, 0.05, 0.16)]:
        cd.ellipse([cx + (ox - rr) * s, cy + (oy - rr) * s, cx + (ox + rr) * s, cy + (oy + rr) * s], fill=255)
    cd.rectangle([cx - 0.2 * s, cy + 0.0 * s, cx + 0.2 * s, cy + 0.21 * s], fill=255)
    paste_fill(c, Image.new("RGB", c.size, (120, 80, 0)), cl.filter(ImageFilter.MaxFilter(5)))
    paste_fill(c, Image.new("RGB", c.size, (255, 255, 255)), cl)


def draw_card_back(c, cx, cy, w, h, badge=None):
    x0, y0 = cx - w / 2, cy - h / 2
    d = ImageDraw.Draw(c)
    d.rounded_rectangle([x0 + 5, y0 + 7, x0 + w + 5, y0 + h + 7], radius=10, fill=(90, 80, 70))
    d.rounded_rectangle([x0, y0, x0 + w, y0 + h], radius=10, fill=(18, 18, 20), outline=(70, 70, 75), width=3)
    pr = int(w * 0.32)
    paste_fill(c, radial(2 * pr, (235, 120, 60), (120, 40, 20), center=(0.35, 0.35)), ellipse_mask(2 * pr, 2 * pr),
               (int(cx - pr), int(cy - pr)))
    if badge == "Venus":
        draw_venus_tag(c, int(x0 + w - 4), int(y0 + 6), int(w * 0.26))
    elif badge == "Floater":
        draw_floater(c, x0 + w - 6, y0 + 8, w * 0.42)


def draw_venus_gauge(c, cx, cy, r, v_col=(255, 255, 255)):
    """The Venus scale icon: a coloured half ring with a V."""
    d = ImageDraw.Draw(c)
    cols = [(200, 40, 30), (235, 110, 20), (245, 190, 30), (150, 190, 40), (60, 150, 50)]
    box = [cx - r, cy - r, cx + r, cy + r]
    d.pieslice([box[0] - 4, box[1] - 4, box[2] + 4, box[3] + 4], 180, 360, fill=(60, 40, 20))
    for i, col in enumerate(cols):
        d.pieslice(box, 180 + i * 36, 180 + (i + 1) * 36, fill=col)
    ri = r * 0.55
    d.pieslice([cx - ri - 4, cy - ri - 4, cx + ri + 4, cy + ri + 4], 180, 360, fill=(60, 40, 20))
    d.pieslice([cx - ri, cy - ri, cx + ri, cy + ri], 180, 360, fill=(250, 235, 200))
    fancy_text(c, (cx, cy - r * 0.05), "V", font("arialbd.ttf", r * 1.15), [(0, v_col), (1, (215, 225, 240))],
               (40, 40, 60), max(2, r // 14), anchor="ms")


def draw_red_arrow(c, x0, cy, w, h):
    pts = [(x0, cy - h * 0.18), (x0 + w * 0.6, cy - h * 0.18), (x0 + w * 0.6, cy - h * 0.5), (x0 + w, cy),
           (x0 + w * 0.6, cy + h * 0.5), (x0 + w * 0.6, cy + h * 0.18), (x0, cy + h * 0.18)]
    paste_fill(c, Image.new("RGB", c.size, (120, 20, 10)), poly_mask(c.size, pts).filter(ImageFilter.MaxFilter(5)))
    g = Image.new("RGB", c.size)
    g.paste(gradient(int(w), int(h), [(0, (255, 120, 60)), (0.5, (225, 50, 20)), (1, (170, 25, 10))]),
            (int(x0), int(cy - h / 2)))
    paste_fill(c, g, poly_mask(c.size, pts))


def draw_label_bar(c, cx, y0, w, h, label):
    """Blue EFFECT / ACTION header bar."""
    x0 = cx - w / 2
    m = Image.new("L", c.size, 0)
    ImageDraw.Draw(m).rounded_rectangle([x0, y0, x0 + w, y0 + h], radius=h * 0.25, fill=255)
    g = Image.new("RGB", c.size)
    g.paste(gradient(int(w), int(h), [(0, (150, 205, 255)), (0.45, (40, 120, 220)), (1, (20, 70, 160))]),
            (int(x0), int(y0)))
    paste_fill(c, g, m)
    ImageDraw.Draw(c).rounded_rectangle([x0, y0, x0 + w, y0 + h], radius=h * 0.25, outline=(15, 45, 110), width=3)
    fancy_text(c, (cx, y0 + h / 2 + 1), label, font("arialbd.ttf", h * 0.62), [(0, (255, 255, 255)), (1, (225, 235, 255))],
               (10, 30, 80), 2, anchor="mm", spacing=h * 0.12)


def draw_rules_box(c, x0, y0, x1, y1, label):
    """Silver bevelled box; the client overlays the rules text in its lower part."""
    d = ImageDraw.Draw(c)
    sh = Image.new("L", c.size, 0)
    ImageDraw.Draw(sh).rectangle([x0 + 8, y0 + 10, x1 + 8, y1 + 10], fill=150)
    paste_fill(c, Image.new("RGB", c.size, (40, 40, 40)), sh.filter(ImageFilter.GaussianBlur(8)))
    c.paste(gradient(x1 - x0, y1 - y0, METAL, vertical=False), (x0, y0))
    b = 16
    inner = gradient(x1 - x0 - 2 * b, y1 - y0 - 2 * b,
                     [(0, (246, 247, 250)), (0.5, (226, 228, 233)), (1, (200, 203, 210))])
    sheen = gradient(inner.size[0], inner.size[1], [(0, (255, 255, 255)), (0.5, (205, 208, 214)), (1, (245, 246, 248))],
                     vertical=False)
    c.paste(Image.blend(inner, sheen, 0.35), (x0 + b, y0 + b))
    d.rectangle([x0, y0, x1, y1], outline=(80, 82, 88), width=3)
    d.rectangle([x0 + b, y0 + b, x1 - b, y1 - b], outline=(120, 122, 130), width=3)
    draw_label_bar(c, (x0 + x1) // 2, y0 + 12, int((x1 - x0) * 0.66), 46, label)


def draw_side_badge(c, cx, cy):
    """Small Venus expansion marker on the left edge of the frame."""
    d = ImageDraw.Draw(c)
    d.ellipse([cx - 20, cy - 20, cx + 20, cy + 20], fill=(255, 255, 255))
    d.ellipse([cx - 16, cy - 16, cx + 16, cy + 16], fill=(40, 110, 210))
    d.text((cx, cy + 1), "V", font=font("arialbd.ttf", 26), fill=(255, 255, 255), anchor="mm")


# ---------------------------------------------------------------------------------------------------------
# logos: each returns a transparent RGBA image cropped to its content


def crop(im):
    return im.crop(im.getbbox())


def logo_msi(row=True):
    c = Image.new("RGBA", (1400, 420), (0, 0, 0, 0))
    r = 150
    cx, cy = 170, 210
    paste_fill(c, Image.new("RGB", c.size, (15, 15, 30)),
               ellipse_mask(*c.size, [cx - r + 6, cy - r + 10, cx + r + 6, cy + r + 10]).filter(
                   ImageFilter.GaussianBlur(8)).point(lambda v: v // 2))
    paste_fill(c, gradient(2 * r + 16, 2 * r + 16, METAL), ellipse_mask(2 * r + 16, 2 * r + 16), (cx - r - 8, cy - r - 8))
    paste_fill(c, radial(2 * r, (90, 150, 240), (8, 25, 90), center=(0.4, 0.3), power=0.9), ellipse_mask(2 * r, 2 * r),
               (cx - r, cy - r))
    # highlight
    hl = ellipse_mask(*c.size, [cx - r * 0.75, cy - r * 0.9, cx + r * 0.55, cy - r * 0.15]).filter(
        ImageFilter.GaussianBlur(14)).point(lambda v: v * 45 // 100)
    paste_fill(c, Image.new("RGB", c.size, (255, 255, 255)), hl)
    fancy_text(c, (cx + 4, cy + 30), "MSi", font("arialbd.ttf", 130), [(0, (255, 255, 255)), (1, (200, 210, 230))],
               (20, 30, 80), 4, anchor="mm")
    star = [(cx - 40 + 34 * math.cos(math.radians(-90 + i * 36)) * (1 if i % 2 == 0 else 0.42),
             cy - 80 + 34 * math.sin(math.radians(-90 + i * 36)) * (1 if i % 2 == 0 else 0.42)) for i in range(10)]
    ImageDraw.Draw(c).polygon(star, fill=(255, 255, 255))
    if row:
        f = fit_size("MORNING STAR INC.", "ariblk.ttf", 96, 1400 - 380, spacing=4)
        fancy_text(c, (370, cy + 10), "MORNING STAR INC.", f,
                   [(0, (250, 250, 252)), (0.5, (175, 178, 186)), (1, (235, 236, 240))], (55, 58, 66), 5, anchor="lm",
                   shadow=4, spacing=4)
    return crop(c)


def logo_manutech(row=True):
    c = Image.new("RGBA", (1400, 420), (0, 0, 0, 0))
    tri = [(40, 40), (420, 210), (40, 380)]
    paste_fill(c, Image.new("RGB", c.size, (60, 10, 5)), poly_mask(c.size, [(x + 8, y + 10) for x, y in tri]).filter(
        ImageFilter.GaussianBlur(8)).point(lambda v: v // 2))
    g = gradient(1400, 420, [(0, (255, 90, 60)), (1, (190, 25, 15))])
    tm = poly_mask(c.size, tri)
    paste_fill(c, g, tm)
    f = font("ariblk.ttf", 120)
    letters = text_mask(c.size, (70, 260), "MANUTECH", f, "ls", spacing=48)
    outline = text_mask(c.size, (70, 260), "MANUTECH", f, "ls", 4, spacing=48)
    red = ImageChops.subtract(letters, tm)
    white = ImageChops.multiply(letters, tm)
    paste_fill(c, Image.new("RGB", c.size, (110, 10, 5)), ImageChops.subtract(outline, tm))
    paste_fill(c, Image.new("RGB", c.size, (225, 35, 25)), red)
    paste_fill(c, Image.new("RGB", c.size, (255, 255, 255)), white)
    return crop(c)


def _venus_symbol(c, cx, cy, r):
    """Big orange/gold Venus symbol: ring with a cross below."""
    m = Image.new("L", c.size, 0)
    d = ImageDraw.Draw(m)
    t = r * 0.34
    d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=255)
    d.ellipse([cx - r + t, cy - r + t, cx + r - t, cy + r - t], fill=0)
    d.rectangle([cx - t / 2, cy + r - 4, cx + t / 2, cy + r * 1.95], fill=255)
    d.rectangle([cx - r * 0.6, cy + r * 1.3, cx + r * 0.6, cy + r * 1.3 + t], fill=255)
    paste_fill(c, Image.new("RGB", c.size, (120, 50, 0)), m.filter(ImageFilter.MaxFilter(9)))
    g = Image.new("RGB", c.size)
    g.paste(gradient(int(2 * r), int(r * 3), [(0, (255, 225, 90)), (0.5, (250, 160, 20)), (1, (215, 90, 10))]),
            (int(cx - r), int(cy - r)))
    paste_fill(c, g, m)


def logo_aphrodite(row=False):
    c = Image.new("RGBA", (1400, 520), (0, 0, 0, 0))
    f = font("ariblk.ttf", 140)
    stops = [(0, (255, 235, 110)), (0.45, (250, 170, 20)), (1, (220, 90, 10))]
    if row:
        _venus_symbol(c, 110, 150, 90)
        fancy_text(c, (240, 260), "APHRODITE", f, stops, (90, 30, 0), 7, anchor="lm", shadow=5)
    else:
        _venus_symbol(c, 700, 120, 105)
        fancy_text(c, (700, 400), "APHRODITE", f, stops, (90, 30, 0), 7, anchor="mm", shadow=5)
    return crop(c)


def _viron_gear(c, cx, cy, r):
    """Half gear with three balls, the Viron emblem."""
    m = Image.new("L", c.size, 0)
    d = ImageDraw.Draw(m)
    d.pieslice([cx - r, cy - r, cx + r, cy + r], 180, 360, fill=255)
    for a in range(195, 360, 30):
        t = math.radians(a)
        tx, ty = cx + math.cos(t) * r, cy + math.sin(t) * r
        d.ellipse([tx - r * 0.17, ty - r * 0.17, tx + r * 0.17, ty + r * 0.17], fill=255)
    d.pieslice([cx - r * 0.45, cy - r * 0.45, cx + r * 0.45, cy + r * 0.45], 180, 360, fill=0)
    for ox, oy, rr in [(-1.0, -1.25, 0.28), (0, -1.55, 0.32), (1.0, -1.25, 0.28)]:
        d.ellipse([cx + (ox - rr) * r, cy + (oy - rr) * r, cx + (ox + rr) * r, cy + (oy + rr) * r], fill=255)
    paste_fill(c, Image.new("RGB", c.size, (15, 15, 18)), m.filter(ImageFilter.MaxFilter(5)))
    g = Image.new("RGB", c.size)
    g.paste(gradient(int(2.8 * r), int(2.4 * r), [(0, (160, 162, 170)), (0.45, (60, 62, 68)), (0.55, (25, 25, 28)),
                                                  (1, (100, 102, 110))]), (int(cx - 1.4 * r), int(cy - 1.95 * r)))
    paste_fill(c, g, m)


def logo_viron(row=False):
    c = Image.new("RGBA", (1400, 520), (0, 0, 0, 0))
    f = font("ariblk.ttf", 190)
    stops = [(0, (170, 172, 180)), (0.45, (55, 56, 62)), (0.55, (15, 15, 18)), (1, (85, 87, 95))]
    if row:
        _viron_gear(c, 150, 300, 110)
        fancy_text(c, (310, 260), "VIRON", f, stops, (235, 235, 240), 4, anchor="lm", shadow=6)
    else:
        _viron_gear(c, 330, 250, 130)
        fancy_text(c, (330, 370), "VIRON", f, stops, (235, 235, 240), 4, anchor="mm", shadow=6)
    return crop(c)


def logo_celestic(row=True):
    c = Image.new("RGBA", (1400, 420), (0, 0, 0, 0))
    cy, r = 210, 175
    # orange half disc behind, blue crescent in front
    om = Image.new("L", c.size, 0)
    ImageDraw.Draw(om).pieslice([60, cy - r, 60 + 2 * r, cy + r], 90, 270, fill=255)
    og = Image.new("RGB", c.size)
    og.paste(gradient(r, 2 * r, [(0, (250, 200, 120)), (1, (225, 130, 40))], vertical=False), (60, cy - r))
    paste_fill(c, og, om)
    bx = 60 + r + 40  # blue half disc, a little larger and shifted right
    bm = Image.new("L", c.size, 0)
    ImageDraw.Draw(bm).pieslice([bx - r - 15, cy - r - 15, bx + r + 15, cy + r + 15], 270, 450, fill=255)
    bg = Image.new("RGB", c.size)
    bg.paste(gradient(r + 15, 2 * r + 30, [(0, (215, 240, 255)), (0.5, (90, 175, 240)), (1, (30, 100, 200))],
                      vertical=False), (bx, cy - r - 15))
    paste_fill(c, bg, bm)
    f = font("georgiab.ttf", 170)
    fancy_text(c, (120, cy + 70), "CELESTIC", f, [(0, (60, 50, 45)), (1, (15, 12, 10))], (255, 255, 255), 3,
               anchor="ls", shadow=5)
    return crop(c)


# num, name, tags, starting M€, label of the rules box
CORPS = [
    (14, "Aphrodite", ["Venus", "Plant"], 47, "EFFECT"),
    (15, "Celestic", ["Venus"], 42, "ACTION"),
    (16, "Manutech", ["Building"], 35, "EFFECT"),
    (17, "Morning Star Inc.", ["Venus"], 50, "EFFECT"),
    (18, "Viron", ["Microbe"], 48, "ACTION"),
]

LOGOS = {14: logo_aphrodite, 15: logo_celestic, 16: logo_manutech, 17: logo_msi, 18: logo_viron}


# ---------------------------------------------------------------------------------------------------------
# backgrounds (inner area, kept pale so the overlaid text stays readable)


def bg_msi(w, h):
    im = gradient(w, h, [(0, (218, 228, 245)), (0.5, (240, 244, 250)), (1, (250, 251, 253))]).convert("RGBA")
    o = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(o)
    for i in range(9):
        r = 120 + i * 70
        d.ellipse([w * 0.62 - r, h * 0.05 - r, w * 0.62 + r, h * 0.05 + r], outline=(150, 170, 210, 70), width=3)
    for x in range(0, w, 60):
        d.line([(x, 0), (x - h * 0.6, h)], fill=(255, 255, 255, 50), width=2)
    im.alpha_composite(o.filter(ImageFilter.GaussianBlur(2)))
    return im


def bg_manutech(w, h):
    im = gradient(w, h, [(0, (246, 232, 220)), (0.6, (250, 245, 240)), (1, (252, 250, 248))]).convert("RGBA")
    o = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(o)
    rnd = random.Random(16)
    for i in range(7):  # faded workers: helmets and shoulders
        x = 200 + i * 220 + rnd.randint(-40, 40)
        y = 140 + rnd.randint(-30, 60)
        d.ellipse([x - 70, y - 60, x + 70, y + 50], fill=(255, 250, 245, 120))
        d.ellipse([x - 150, y + 70, x + 150, y + 520], fill=(240, 140, 70, 55))
    im.alpha_composite(o.filter(ImageFilter.GaussianBlur(45)))
    return im


def bg_aphrodite(w, h):
    im = gradient(w, h, [(0, (225, 225, 220)), (1, (245, 244, 240))]).convert("RGBA")
    pr = 560
    planet = radial(2 * pr, (245, 205, 130), (95, 150, 105), center=(0.4, 0.35), power=0.7)
    pd = ImageDraw.Draw(planet, "RGBA")
    for i, y in enumerate(range(80, 2 * pr, 120)):
        pd.ellipse([-pr // 2, y - 40, pr * 2.5, y + 40], fill=(170, 120, 70, 60) if i % 2 else (230, 220, 190, 60))
    planet = planet.filter(ImageFilter.GaussianBlur(10))
    mask = ellipse_mask(2 * pr, 2 * pr).filter(ImageFilter.GaussianBlur(12)).point(lambda v: v * 45 // 100)
    planet.putalpha(mask)
    layer = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    layer.alpha_composite(planet, (w // 2 - pr + 60, 0), (0, pr // 3 - 60))
    im.alpha_composite(layer)
    fade = gradient(w, h, [(0, (0, 0, 0)), (0.55, (0, 0, 0)), (1, (255, 255, 255))]).convert("L")
    im.alpha_composite(Image.merge("RGBA", [Image.new("L", (w, h), 250)] * 3 + [fade.point(lambda v: v * 7 // 10)]))
    return im


def bg_viron(w, h):
    im = gradient(w, h, [(0, (232, 222, 208)), (1, (240, 233, 222))]).convert("RGBA")
    noise = Image.effect_noise((w // 4, h // 4), 40).resize((w, h), Image.BICUBIC).filter(ImageFilter.GaussianBlur(3))
    shade = Image.merge("RGBA", [Image.new("L", (w, h), 150), Image.new("L", (w, h), 120), Image.new("L", (w, h), 90),
                                 noise.point(lambda v: max(0, v - 110) // 3)])
    im.alpha_composite(shade)
    return im


def bg_celestic(w, h):
    im = gradient(w, h, [(0, (238, 222, 205)), (0.5, (230, 205, 182)), (1, (244, 234, 224))]).convert("RGBA")
    o = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(o)
    rnd = random.Random(15)
    for i in range(14):
        y = rnd.randint(0, h)
        d.ellipse([rnd.randint(-400, w // 2), y - 30, rnd.randint(w // 2, w + 400), y + 30],
                  fill=(255, 245, 235, 110) if i % 2 else (200, 150, 110, 60))
    im.alpha_composite(o.filter(ImageFilter.GaussianBlur(30)))
    return im


BACKGROUNDS = {14: bg_aphrodite, 15: bg_celestic, 16: bg_manutech, 17: bg_msi, 18: bg_viron}


# ---------------------------------------------------------------------------------------------------------
# per corp content (start row at y ~ 0.52 H, effect icons at y ~ 0.555 H)

START_Y = int(H * 0.52)
FX_Y = int(H * 0.555)
FX_X = int(W * 0.745)


def content_14(c):  # Aphrodite
    draw_megacredit(c, 230, START_Y, 140, 47)
    draw_prod_box(c, 470, START_Y, 160, draw_plant_res)
    draw_venus_gauge(c, FX_X - 130, FX_Y + 45, 80)
    ImageDraw.Draw(c).text((FX_X, FX_Y), ":", font=font("ariblk.ttf", 90), fill=(20, 20, 20), anchor="mm")
    draw_megacredit(c, FX_X + 110, FX_Y, 100, 2)


def content_15(c):  # Celestic
    draw_megacredit(c, 200, START_Y, 140, 42)
    draw_card_back(c, 400, START_Y + 5, 110, 150, "Floater")
    draw_card_back(c, 545, START_Y + 5, 110, 150, "Floater")
    draw_red_arrow(c, FX_X - 190, FX_Y, 170, 90)
    draw_floater(c, FX_X + 80, FX_Y, 110)
    ImageDraw.Draw(c).text((FX_X + 150, FX_Y - 50), "*", font=font("ariblk.ttf", 60), fill=(20, 20, 20), anchor="mm")
    # VP disc, bottom right
    cx, cy, r = W - 150, H - 140, 100
    paste_fill(c, Image.new("RGB", c.size, (20, 10, 5)),
               ellipse_mask(*c.size, [cx - r + 6, cy - r + 10, cx + r + 6, cy + r + 10]).filter(
                   ImageFilter.GaussianBlur(8)).point(lambda v: v // 2))
    paste_fill(c, radial(2 * r, (205, 140, 90), (90, 45, 20), center=(0.4, 0.35)), ellipse_mask(2 * r, 2 * r),
               (cx - r, cy - r))
    d = ImageDraw.Draw(c)
    d.ellipse([cx - r, cy - r, cx + r, cy + r], outline=(50, 25, 10), width=5)
    d.text((cx - 30, cy + 4), "1/3", font=font("ariblk.ttf", 66), fill=(30, 15, 5), anchor="mm")
    draw_floater(c, cx + 52, cy, 56)


def content_16(c):  # Manutech
    draw_prod_box(c, 230, START_Y, 160, draw_steel_res)
    draw_megacredit(c, 480, START_Y, 140, 35)
    draw_prod_box(c, FX_X - 100, FX_Y, 110, draw_mystery_res)
    ImageDraw.Draw(c).text((FX_X, FX_Y), ":", font=font("ariblk.ttf", 90), fill=(20, 20, 20), anchor="mm")
    draw_mystery_res(c, FX_X + 100, FX_Y, 80)


def content_17(c):  # Morning Star Inc.
    draw_megacredit(c, 200, START_Y, 140, 50)
    for i in range(3):
        draw_card_back(c, 390 + i * 135, START_Y + 5, 110, 150, "Venus")
    x0, y0 = FX_X - 230, FX_Y - 55
    d = ImageDraw.Draw(c)
    d.rectangle([x0, y0, x0 + 180, y0 + 110], fill=(250, 200, 40), outline=(150, 90, 0), width=4)
    draw_venus_gauge(c, x0 + 90, y0 + 98, 78)
    fancy_text(c, (FX_X + 70, FX_Y), ":+/-2", font("ariblk.ttf", 92), [(0, (30, 30, 30)), (1, (0, 0, 0))],
               anchor="mm")


def content_18(c):  # Viron
    draw_megacredit(c, 380, START_Y + 40, 140, 48)
    draw_red_arrow(c, FX_X - 230, FX_Y + 10, 170, 90)
    draw_label_bar(c, FX_X + 100, FX_Y - 52, 240, 32, "ACTION")
    draw_red_arrow(c, FX_X + 20, FX_Y + 22, 170, 90)


CONTENT = {14: content_14, 15: content_15, 16: content_16, 17: content_17, 18: content_18}
LOGO_BOX = {  # where the logo goes on the card: x0, y0, x1, y1
    14: (420, 90, 1270, 500),
    15: (110, 110, 1150, 440),
    16: (130, 120, 1350, 420),
    17: (130, 80, 1400, 410),
    18: (100, 90, 900, 520),
}


# ---------------------------------------------------------------------------------------------------------


def fit_into(im, w, h):
    k = min(w / im.size[0], h / im.size[1])
    return im.resize((max(1, int(im.size[0] * k)), max(1, int(im.size[1] * k))), Image.LANCZOS)


def make_card(num, name, tags, mc, box_label):
    c = Image.new("RGBA", (W, H), (255, 255, 255, 255))
    d = ImageDraw.Draw(c)

    # silver frame
    fm = Image.new("L", (W, H), 0)
    ImageDraw.Draw(fm).rounded_rectangle([PAD, PAD, W - PAD, H - PAD], radius=86, fill=255)
    paste_fill(c, gradient(W, H, METAL, vertical=False), fm)
    blend = gradient(W, H, [(0, (250, 250, 252)), (0.5, (140, 142, 150)), (1, (215, 217, 222))])
    paste_fill(c, blend, fm.point(lambda v: v * 4 // 10))
    d.rounded_rectangle([PAD, PAD, W - PAD, H - PAD], radius=86, outline=(80, 82, 90), width=4)

    # inner background
    iw, ih = IN1 - IN0, H - 2 * IN0
    im = Image.new("L", (iw, ih), 0)
    ImageDraw.Draw(im).rounded_rectangle([0, 0, iw - 1, ih - 1], radius=58, fill=255)
    c.paste(BACKGROUNDS[num](iw, ih).convert("RGB"), (IN0, IN0), im)
    d.rounded_rectangle([IN0, IN0, IN1, H - IN0], radius=58, outline=(95, 97, 105), width=4)

    # CORPORATION banner
    bw, bh = 440, 50
    bx = (W - bw) // 2
    pts = [(bx - 24, 10), (bx + bw + 24, 10), (bx + bw, 10 + bh), (bx, 10 + bh)]
    paste_fill(c, Image.new("RGB", c.size, (130, 60, 0)), poly_mask(c.size, pts).filter(ImageFilter.MaxFilter(5)))
    g = Image.new("RGB", c.size)
    g.paste(gradient(bw + 48, bh, [(0, (255, 215, 90)), (0.5, (245, 155, 20)), (1, (215, 110, 5))]), (bx - 24, 10))
    paste_fill(c, g, poly_mask(c.size, pts))
    d.text((W // 2, 10 + bh // 2 + 1), "C O R P O R A T I O N", font=font("arialbd.ttf", 30), fill=(100, 40, 0),
           anchor="mm")

    # logo
    x0, y0, x1, y1 = LOGO_BOX[num]
    logo = fit_into(LOGOS[num](row=num not in (14, 18)), x1 - x0, y1 - y0)
    c.alpha_composite(logo, (x0 + (x1 - x0 - logo.size[0]) // 2 if num == 14 else x0,
                             y0 + (y1 - y0 - logo.size[1]) // 2))

    # tags in their bracket, top right
    r = 72
    cy = 150
    xs = [W - 120 - i * (2 * r + 22) for i in range(len(tags))]
    draw_tag_bracket(c, xs[-1] - r - 10, cy, 2 * r - 10)
    for x, tag in zip(xs, reversed(tags)):
        draw_tag(c, x, cy, r, tag)

    draw_side_badge(c, IN0, int(H * 0.76))

    # rules box, right; the client puts the effect / action text in it (top 60%)
    bottom = 0.80 if num == 15 else 0.93
    draw_rules_box(c, int(W * 0.535), int(H * 0.455), int(W * 0.955), int(H * bottom), box_label)

    CONTENT[num](c)
    return c.convert("RGB").resize((CW, CH), Image.LANCZOS)


def make_logo(num):
    logo = Image.new("RGBA", (LW * S, LH * S), (0, 0, 0, 0))
    im = fit_into(LOGOS[num](row=True), LW * S - 40, LH * S - 30)
    logo.alpha_composite(im, ((LW * S - im.size[0]) // 2, (LH * S - im.size[1]) // 2))
    return logo.resize((LW, LH), Image.LANCZOS)


def main():
    os.makedirs(OUT, exist_ok=True)
    cards = Image.new("RGB", (CW * len(CORPS), CH), (255, 255, 255))
    logos = Image.new("RGBA", (LW * len(CORPS), LH), (0, 0, 0, 0))
    for i, (num, name, tags, mc, label) in enumerate(CORPS):
        cards.paste(make_card(num, name, tags, mc, label), (i * CW, 0))
        logos.alpha_composite(make_logo(num), (i * LW, 0))
    cards.save(os.path.join(OUT, "venus_corps.jpg"), quality=88, optimize=True)
    logos.save(os.path.join(OUT, "venus_corps_logo.png"), optimize=True)


if __name__ == "__main__":
    main()
