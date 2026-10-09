from __future__ import annotations

import html
import re
from pathlib import Path
from xml.etree import ElementTree as ET


ROOT = Path(__file__).resolve().parents[1]
SQL_PATH = ROOT / "deliver.sql"
OUTPUT_DIR = ROOT / "assets" / "diagrams"
OUTPUT_PATH = OUTPUT_DIR / "food_sewa_database_schema.drawio"


def parse_tables(sql_text: str) -> dict[str, list[str]]:
    tables: dict[str, list[str]] = {}
    for table_name, body in re.findall(r"CREATE TABLE `([^`]+)` \((.*?)\n\) ENGINE=", sql_text, re.S):
        columns: list[str] = []
        for line in body.splitlines():
            line = line.strip().rstrip(",")
            match = re.match(r"`([^`]+)`\s+(.+)", line)
            if match:
                columns.append(match.group(1))
        tables[table_name] = columns
    return tables


def parse_foreign_keys(sql_text: str) -> list[tuple[str, str, str]]:
    edges: list[tuple[str, str, str]] = []
    for alter_table, block in re.findall(r"ALTER TABLE `([^`]+)`\s+(.*?);", sql_text, re.S):
        for local_column, ref_table in re.findall(
            r"FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`id`\)",
            block,
        ):
            edges.append((ref_table, alter_table, local_column))
    return edges


def table_label(table_name: str, columns: list[str], fk_columns: set[str]) -> str:
    lines = [f"<b>{html.escape(table_name)}</b>", "<hr size='1'>"]
    for column in columns:
        prefix = "PK " if column == "id" else "FK " if column in fk_columns else ""
        lines.append(html.escape(prefix + column))
    return "<br>".join(lines)


def make_cell(parent: ET.Element, cell_id: str, attrs: dict[str, str]) -> ET.Element:
    cell = ET.SubElement(parent, "mxCell", {"id": cell_id, **attrs})
    return cell


def add_geometry(parent: ET.Element, attrs: dict[str, str]) -> None:
    ET.SubElement(parent, "mxGeometry", {**attrs, "as": "geometry"})


def main() -> None:
    sql_text = SQL_PATH.read_text(encoding="utf-8")
    tables = parse_tables(sql_text)
    edges = parse_foreign_keys(sql_text)
    fk_by_table: dict[str, set[str]] = {name: set() for name in tables}
    for _source, target, column in edges:
        fk_by_table[target].add(column)

    positions = {
        "users": (40, 80, 230, 175, "#1f4e79"),
        "admins": (350, 80, 220, 135, "#666f7a"),
        "addresses": (930, 80, 240, 215, "#1f4e79"),
        "categories": (40, 355, 230, 135, "#548235"),
        "foods": (40, 570, 230, 215, "#548235"),
        "carts": (350, 325, 230, 160, "#2f75b5"),
        "cart_items": (350, 585, 230, 215, "#c55a11"),
        "wishlists": (645, 325, 230, 160, "#2f75b5"),
        "wishlist_items": (645, 585, 230, 200, "#c55a11"),
        "orders": (930, 365, 250, 295, "#7030a0"),
        "order_items": (930, 830, 250, 200, "#c55a11"),
        "payments": (930, 1120, 250, 185, "#7030a0"),
        "coupons": (350, 900, 230, 240, "#9e480e"),
        "coupon_usage": (645, 920, 230, 225, "#c55a11"),
        "system_settings": (350, 1260, 230, 145, "#666f7a"),
    }

    mxfile = ET.Element(
        "mxfile",
        {
            "host": "app.diagrams.net",
            "modified": "2026-05-21T00:00:00.000Z",
            "agent": "Codex",
            "version": "26.0.0",
            "type": "device",
        },
    )
    diagram = ET.SubElement(mxfile, "diagram", {"id": "food-sewa-schema", "name": "FOOD SEWA Database Schema"})
    model = ET.SubElement(
        diagram,
        "mxGraphModel",
        {
            "dx": "1400",
            "dy": "900",
            "grid": "1",
            "gridSize": "10",
            "guides": "1",
            "tooltips": "1",
            "connect": "1",
            "arrows": "1",
            "fold": "1",
            "page": "1",
            "pageScale": "1",
            "pageWidth": "1200",
            "pageHeight": "1560",
            "math": "0",
            "shadow": "0",
        },
    )
    root = ET.SubElement(model, "root")
    ET.SubElement(root, "mxCell", {"id": "0"})
    ET.SubElement(root, "mxCell", {"id": "1", "parent": "0"})

    title = make_cell(
        root,
        "title",
        {
            "value": "<b>FOOD SEWA Database Schema</b><br>deliver database | MySQL InnoDB | editable draw.io diagram",
            "style": "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=top;whiteSpace=wrap;fontSize=22;fontColor=#16233a;",
            "vertex": "1",
            "parent": "1",
        },
    )
    add_geometry(title, {"x": "40", "y": "20", "width": "760", "height": "50"})

    for table_name, columns in tables.items():
        x, y, w, h, color = positions[table_name]
        cell = make_cell(
            root,
            f"table_{table_name}",
            {
                "value": table_label(table_name, columns, fk_by_table[table_name]),
                "style": (
                    "rounded=1;whiteSpace=wrap;html=1;arcSize=8;spacing=8;"
                    f"strokeColor=#25364a;fillColor=#ffffff;fontSize=12;fontColor=#1d2733;"
                    f"swimlaneFillColor={color};startSize=32;fontStyle=0;align=left;"
                ),
                "vertex": "1",
                "parent": "1",
            },
        )
        add_geometry(cell, {"x": str(x), "y": str(y), "width": str(w), "height": str(h)})

    for idx, (source_table, target_table, column) in enumerate(edges, start=1):
        edge = make_cell(
            root,
            f"fk_{idx}_{source_table}_{target_table}_{column}",
            {
                "value": html.escape(column),
                "style": (
                    "edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;"
                    "html=1;endArrow=block;endFill=1;strokeColor=#6f8193;fontSize=10;"
                    "fontColor=#314255;labelBackgroundColor=#eef3f8;"
                ),
                "edge": "1",
                "parent": "1",
                "source": f"table_{source_table}",
                "target": f"table_{target_table}",
            },
        )
        add_geometry(edge, {"relative": "1"})

    legend = make_cell(
        root,
        "legend",
        {
            "value": (
                "<b>Legend</b><br>"
                "PK = Primary Key, FK = Foreign Key<br>"
                "Arrows point from parent tables to child tables using the labelled FK column."
            ),
            "style": "rounded=1;whiteSpace=wrap;html=1;arcSize=8;spacing=10;fillColor=#f8fafc;strokeColor=#c8d2dd;fontSize=12;fontColor=#314255;",
            "vertex": "1",
            "parent": "1",
        },
    )
    add_geometry(legend, {"x": "40", "y": "1440", "width": "1140", "height": "80"})

    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    ET.indent(mxfile, space="  ")
    OUTPUT_PATH.write_text(ET.tostring(mxfile, encoding="unicode", xml_declaration=True), encoding="utf-8")
    print(f"Created {OUTPUT_PATH}")


if __name__ == "__main__":
    main()
