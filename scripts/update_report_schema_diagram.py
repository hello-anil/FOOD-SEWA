from __future__ import annotations

import re
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Inches
from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[1]
SQL_PATH = ROOT / "deliver.sql"
REPORT_PATH = ROOT / "FOOD_SEWA_Final_Report.docx"
DIAGRAM_DIR = ROOT / "assets" / "diagrams"
DIAGRAM_PATH = DIAGRAM_DIR / "database_schema_diagram.png"


def parse_schema(sql_text: str) -> tuple[list[str], list[tuple[str, str, str]]]:
    tables = re.findall(r"CREATE TABLE `([^`]+)`", sql_text)
    fks = re.findall(
        r"ADD CONSTRAINT `[^`]+` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)",
        sql_text,
    )

    fk_edges: list[tuple[str, str, str]] = []
    for local_column, ref_table, _ref_column in fks:
        table_match = re.search(
            rf"ALTER TABLE `([^`]+)`\s+ADD CONSTRAINT `[^`]+` FOREIGN KEY \(`{re.escape(local_column)}`\) REFERENCES `{re.escape(ref_table)}`",
            sql_text,
            re.S,
        )
        if table_match:
            fk_edges.append((table_match.group(1), ref_table, local_column))
    return tables, fk_edges


def load_font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        Path("C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf"),
        Path("C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf"),
    ]
    for candidate in candidates:
        if candidate.exists():
            return ImageFont.truetype(str(candidate), size)
    return ImageFont.load_default()


def draw_arrow(
    draw: ImageDraw.ImageDraw,
    start: tuple[int, int],
    end: tuple[int, int],
    color: str = "#8799aa",
    width: int = 3,
) -> None:
    draw.line([start, end], fill=color, width=width)
    x1, y1 = start
    x2, y2 = end
    dx = x2 - x1
    dy = y2 - y1
    length = max((dx * dx + dy * dy) ** 0.5, 1)
    ux, uy = dx / length, dy / length
    px, py = -uy, ux
    head = 18
    base_x, base_y = x2 - ux * head, y2 - uy * head
    points = [
        (x2, y2),
        (base_x + px * 8, base_y + py * 8),
        (base_x - px * 8, base_y - py * 8),
    ]
    draw.polygon(points, fill=color)


def draw_poly_arrow(
    draw: ImageDraw.ImageDraw,
    points: list[tuple[int, int]],
    color: str = "#8799aa",
    width: int = 3,
) -> None:
    if len(points) < 2:
        return
    for start, end in zip(points, points[1:-1]):
        draw.line([start, end], fill=color, width=width)
    draw_arrow(draw, points[-2], points[-1], color=color, width=width)


def wrap_text(draw: ImageDraw.ImageDraw, text: str, font: ImageFont.ImageFont, width: int) -> list[str]:
    words = text.split()
    lines: list[str] = []
    current = ""
    for word in words:
        candidate = word if not current else f"{current} {word}"
        if draw.textbbox((0, 0), candidate, font=font)[2] <= width:
            current = candidate
        else:
            if current:
                lines.append(current)
            current = word
    if current:
        lines.append(current)
    return lines


def draw_table(
    draw: ImageDraw.ImageDraw,
    xy: tuple[int, int],
    size: tuple[int, int],
    title: str,
    fields: list[str],
    color: str,
    title_font: ImageFont.ImageFont,
    body_font: ImageFont.ImageFont,
) -> dict[str, tuple[int, int]]:
    x, y = xy
    w, h = size
    radius = 14
    draw.rounded_rectangle((x, y, x + w, y + h), radius=radius, fill="#ffffff", outline="#25364a", width=3)
    draw.rounded_rectangle((x, y, x + w, y + 50), radius=radius, fill=color, outline=color)
    draw.rectangle((x, y + 34, x + w, y + 50), fill=color)
    title_bbox = draw.textbbox((0, 0), title, font=title_font)
    draw.text((x + (w - (title_bbox[2] - title_bbox[0])) / 2, y + 12), title, font=title_font, fill="#ffffff")

    text_x = x + 16
    text_y = y + 65
    for field in fields:
        for line in wrap_text(draw, field, body_font, w - 32):
            draw.text((text_x, text_y), line, font=body_font, fill="#1d2733")
            text_y += 25
        text_y += 2

    return {
        "top": (x + w // 2, y),
        "bottom": (x + w // 2, y + h),
        "left": (x, y + h // 2),
        "right": (x + w, y + h // 2),
        "center": (x + w // 2, y + h // 2),
    }


def make_diagram(table_count: int) -> None:
    DIAGRAM_DIR.mkdir(parents=True, exist_ok=True)

    width, height = 1800, 2400
    img = Image.new("RGB", (width, height), "#f6f8fb")
    draw = ImageDraw.Draw(img)
    title_font = load_font(46, True)
    subtitle_font = load_font(28)
    box_title_font = load_font(24, True)
    body_font = load_font(21)

    draw.text((70, 45), "FOOD SEWA Database Schema", font=title_font, fill="#16233a")
    draw.text(
        (72, 105),
        f"deliver database | {table_count} tables | MySQL InnoDB foreign key relationship map",
        font=subtitle_font,
        fill="#4a5d73",
    )

    specs = {
        "users": ((90, 210), (300, 205), "#1f4e79", ["PK id", "full_name, email", "phone, status"]),
        "admins": ((520, 210), (300, 170), "#59636f", ["PK id", "name, email", "status"]),
        "categories": ((90, 560), (300, 170), "#548235", ["PK id", "name", "status"]),
        "foods": ((90, 830), (300, 250), "#548235", ["PK id", "FK category_id", "name, price", "stock_qty, status"]),
        "carts": ((520, 500), (300, 185), "#2f75b5", ["PK id", "FK user_id nullable", "session_id unique"]),
        "cart_items": ((520, 790), (300, 235), "#c55a11", ["PK id", "FK cart_id", "FK food_id", "qty, price_snapshot", "UNIQUE cart_id+food_id"]),
        "wishlists": ((940, 500), (300, 185), "#2f75b5", ["PK id", "FK user_id nullable", "session_id unique"]),
        "wishlist_items": ((940, 790), (300, 235), "#c55a11", ["PK id", "FK wishlist_id", "FK food_id", "UNIQUE wishlist_id+food_id"]),
        "addresses": ((1340, 210), (310, 230), "#1f4e79", ["PK id", "FK user_id", "full_name, phone", "city, area", "lat, lng"]),
        "orders": ((1340, 590), (310, 355), "#7030a0", ["PK id", "FK user_id", "FK address_id", "FK coupon_id nullable", "order_code unique", "total_amount", "payment_status", "delivery_status"]),
        "order_items": ((1340, 1110), (310, 245), "#c55a11", ["PK id", "FK order_id", "FK food_id", "name_snapshot", "price_snapshot, qty"]),
        "payments": ((1340, 1480), (310, 225), "#7030a0", ["PK id", "FK order_id", "gateway", "amount, status", "txn_ref"]),
        "coupons": ((520, 1220), (300, 285), "#9e480e", ["PK id", "code unique", "FK user_id nullable", "discount_percent", "max_uses, used_count", "expires_at, type"]),
        "coupon_usage": ((940, 1240), (300, 255), "#c55a11", ["PK id", "FK coupon_id", "FK user_id", "FK order_id", "UNIQUE coupon_id+order_id"]),
        "system_settings": ((520, 1730), (300, 180), "#59636f", ["PK id", "setting_key unique", "setting_value"]),
    }

    def point(name: str, side: str, offset: int = 0) -> tuple[int, int]:
        (x, y), (w, h), *_ = specs[name]
        if side == "top":
            return (x + w // 2 + offset, y - 8)
        if side == "bottom":
            return (x + w // 2 + offset, y + h + 8)
        if side == "left":
            return (x - 8, y + h // 2 + offset)
        if side == "right":
            return (x + w + 8, y + h // 2 + offset)
        raise ValueError(side)

    edge_color = "#8799aa"
    routes = [
        [point("users", "right", -10), (450, 292), (450, 170), (1495, 170), point("addresses", "top")],
        [point("users", "bottom", -50), point("carts", "top", -40)],
        [point("users", "bottom", 80), (1010, 455), point("wishlists", "top")],
        [point("users", "right", 35), (1290, 545), point("orders", "left", -70)],
        [point("users", "bottom", 0), (450, 1130), point("coupons", "left", -65)],
        [point("categories", "bottom"), point("foods", "top")],
        [point("carts", "bottom"), point("cart_items", "top")],
        [point("wishlists", "bottom"), point("wishlist_items", "top")],
        [point("foods", "right", -65), point("cart_items", "left", -20)],
        [point("foods", "right", 0), point("wishlist_items", "left", 0)],
        [point("foods", "right", 60), (1290, 1245), point("order_items", "left", 10)],
        [point("addresses", "bottom"), point("orders", "top")],
        [point("orders", "bottom"), point("order_items", "top")],
        [point("order_items", "bottom"), point("payments", "top")],
        [point("orders", "bottom", -70), (1260, 1560), point("payments", "left", -20)],
        [point("coupons", "right", 20), point("coupon_usage", "left", 10)],
        [point("coupons", "right", -45), (1320, 1030), point("orders", "left", 75)],
        [point("orders", "left", 95), (1265, 1420), point("coupon_usage", "right", 25)],
    ]
    for route in routes:
        draw_poly_arrow(draw, route, edge_color, width=3)

    for name, (xy, size, color, fields) in specs.items():
        draw_table(draw, xy, size, name, fields, color, box_title_font, body_font)

    legend_x, legend_y = 90, 2060
    legend_items = [
        ("User/Admin", "#1f4e79"),
        ("Product", "#548235"),
        ("Order/Payment", "#7030a0"),
        ("Junction", "#c55a11"),
        ("Configuration", "#59636f"),
    ]
    draw.rounded_rectangle((legend_x, legend_y, 1660, 2240), radius=16, fill="#ffffff", outline="#c8d2dd", width=2)
    draw.text((legend_x + 25, legend_y + 22), "Legend", font=box_title_font, fill="#16233a")
    x = legend_x + 25
    y = legend_y + 72
    for label, color in legend_items:
        draw.rounded_rectangle((x, y, x + 28, y + 28), radius=6, fill=color)
        draw.text((x + 42, y + 1), label, font=body_font, fill="#1d2733")
        x += 290
    draw.text(
        (legend_x + 25, legend_y + 125),
        "Arrows point from parent tables to child tables. Foreign key columns are listed inside each child table.",
        font=body_font,
        fill="#4a5d73",
    )

    img.save(DIAGRAM_PATH, quality=95)


def replace_schema_placeholder(table_count: int) -> None:
    doc = Document(REPORT_PATH)

    for para in doc.paragraphs:
        if "comprises 14 InnoDB tables" in para.text:
            para.text = para.text.replace("comprises 14 InnoDB tables", f"comprises {table_count} InnoDB tables")
            for run in para.runs:
                run.font.name = "Times New Roman"

    target_text = "[IMAGE PLACEHOLDER: Figure 8: Database Schema Diagram Showing Tables and Foreign Key Relationships]"
    caption_text = "Figure 8: Database Schema Diagram Showing Tables and Foreign Key Relationships"
    for para in doc.paragraphs:
        if target_text in para.text:
            para.clear()
            para.alignment = WD_ALIGN_PARAGRAPH.CENTER
            run = para.add_run()
            run.add_picture(str(DIAGRAM_PATH), width=Inches(6.0))
            break
    else:
        for idx, para in enumerate(doc.paragraphs):
            if para.text.strip() == caption_text and idx > 0:
                # The same text appears in the List of Figures. Only patch the
                # real figure location inside Chapter 3.
                if idx < 200:
                    continue
                image_para = doc.paragraphs[idx - 1]
                image_para.clear()
                image_para.alignment = WD_ALIGN_PARAGRAPH.CENTER
                run = image_para.add_run()
                run.add_picture(str(DIAGRAM_PATH), width=Inches(6.0))
                break
        else:
            raise RuntimeError("Figure 8 placeholder/caption was not found in the report.")

    doc.save(REPORT_PATH)


def main() -> None:
    sql_text = SQL_PATH.read_text(encoding="utf-8")
    tables, fks = parse_schema(sql_text)
    if len(tables) != 15:
        raise RuntimeError(f"Expected 15 tables in deliver.sql, found {len(tables)}.")
    if len(fks) < 15:
        raise RuntimeError(f"Expected foreign keys to be parsed, found {len(fks)}.")

    make_diagram(len(tables))
    replace_schema_placeholder(len(tables))
    print(f"Updated report: {REPORT_PATH}")
    print(f"Inserted diagram: {DIAGRAM_PATH}")


if __name__ == "__main__":
    main()
