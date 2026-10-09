from __future__ import annotations

import html
import re
from pathlib import Path
from xml.etree import ElementTree as ET


ROOT = Path(__file__).resolve().parents[1]
SQL_PATH = ROOT / "deliver.sql"
OUTPUT_DIR = ROOT / "assets" / "diagrams"
OUTPUT_PATH = OUTPUT_DIR / "food_sewa_er_diagram.drawio"


ENTITY_STYLE = (
    "shape=mxgraph.er.entity;whiteSpace=wrap;html=1;fillColor=#ffffff;"
    "strokeColor=#25364a;fontColor=#16233a;fontStyle=1;fontSize=14;"
)
ATTRIBUTE_STYLE = (
    "shape=mxgraph.er.attribute;whiteSpace=wrap;html=1;fillColor=#f8fafc;"
    "strokeColor=#6f8193;fontColor=#1d2733;fontSize=11;"
)
KEY_ATTRIBUTE_STYLE = (
    "shape=mxgraph.er.attribute;whiteSpace=wrap;html=1;fillColor=#fff2cc;"
    "strokeColor=#bf9000;fontColor=#1d2733;fontStyle=4;fontSize=11;"
)
FK_ATTRIBUTE_STYLE = (
    "shape=mxgraph.er.attribute;whiteSpace=wrap;html=1;fillColor=#eaf3ff;"
    "strokeColor=#2f75b5;fontColor=#1d2733;fontSize=11;"
)
RELATIONSHIP_STYLE = (
    "shape=mxgraph.er.relationship;whiteSpace=wrap;html=1;fillColor=#f4eafa;"
    "strokeColor=#7030a0;fontColor=#4c1d70;fontStyle=1;fontSize=12;"
)
LINE_STYLE = "endArrow=none;html=1;rounded=0;strokeColor=#6f8193;fontSize=10;fontColor=#314255;"


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


def make_cell(parent: ET.Element, cell_id: str, attrs: dict[str, str]) -> ET.Element:
    return ET.SubElement(parent, "mxCell", {"id": cell_id, **attrs})


def add_geometry(parent: ET.Element, attrs: dict[str, str]) -> None:
    ET.SubElement(parent, "mxGeometry", {**attrs, "as": "geometry"})


def add_vertex(
    root: ET.Element,
    cell_id: str,
    value: str,
    style: str,
    x: int,
    y: int,
    width: int,
    height: int,
) -> None:
    cell = make_cell(
        root,
        cell_id,
        {
            "value": value,
            "style": style,
            "vertex": "1",
            "parent": "1",
        },
    )
    add_geometry(cell, {"x": str(x), "y": str(y), "width": str(width), "height": str(height)})


def add_edge(
    root: ET.Element,
    cell_id: str,
    source: str,
    target: str,
    value: str = "",
    style: str = LINE_STYLE,
) -> None:
    cell = make_cell(
        root,
        cell_id,
        {
            "value": value,
            "style": style,
            "edge": "1",
            "parent": "1",
            "source": source,
            "target": target,
        },
    )
    add_geometry(cell, {"relative": "1"})


def main() -> None:
    sql_text = SQL_PATH.read_text(encoding="utf-8")
    tables = parse_tables(sql_text)
    fks = parse_foreign_keys(sql_text)
    fk_columns: dict[str, set[str]] = {table: set() for table in tables}
    for _parent, child, column in fks:
        fk_columns[child].add(column)

    entity_positions = {
        "users": (90, 150),
        "admins": (500, 150),
        "addresses": (910, 150),
        "categories": (1320, 150),
        "foods": (90, 450),
        "carts": (500, 450),
        "cart_items": (910, 450),
        "wishlists": (1320, 450),
        "wishlist_items": (90, 750),
        "orders": (500, 750),
        "order_items": (910, 750),
        "payments": (1320, 750),
        "coupons": (90, 1050),
        "coupon_usage": (500, 1050),
        "system_settings": (910, 1050),
    }

    display_attributes = {
        "users": ["id", "full_name", "email", "phone"],
        "admins": ["id", "name", "email", "status"],
        "addresses": ["id", "user_id", "address_name", "lat/lng"],
        "categories": ["id", "name", "status"],
        "foods": ["id", "category_id", "name", "price"],
        "carts": ["id", "user_id", "session_id"],
        "cart_items": ["id", "cart_id", "food_id", "qty"],
        "wishlists": ["id", "user_id", "session_id"],
        "wishlist_items": ["id", "wishlist_id", "food_id"],
        "orders": ["id", "user_id", "address_id", "coupon_id"],
        "order_items": ["id", "order_id", "food_id", "qty"],
        "payments": ["id", "order_id", "gateway", "status"],
        "coupons": ["id", "code", "user_id", "type"],
        "coupon_usage": ["id", "coupon_id", "user_id", "order_id"],
        "system_settings": ["id", "setting_key", "setting_value"],
    }

    relationships = [
        ("users", "addresses", "has", "1", "N"),
        ("users", "carts", "owns", "1", "0..1"),
        ("users", "wishlists", "owns", "1", "0..1"),
        ("users", "orders", "places", "1", "N"),
        ("users", "coupons", "receives", "1", "N"),
        ("users", "coupon_usage", "uses", "1", "N"),
        ("categories", "foods", "contains", "1", "N"),
        ("foods", "cart_items", "selected in", "1", "N"),
        ("foods", "wishlist_items", "saved in", "1", "N"),
        ("foods", "order_items", "ordered as", "1", "N"),
        ("carts", "cart_items", "contains", "1", "N"),
        ("wishlists", "wishlist_items", "contains", "1", "N"),
        ("addresses", "orders", "ships to", "1", "N"),
        ("orders", "order_items", "contains", "1", "N"),
        ("orders", "payments", "paid by", "1", "N"),
        ("orders", "coupon_usage", "records", "1", "N"),
        ("coupons", "orders", "applied to", "0..1", "N"),
        ("coupons", "coupon_usage", "logged by", "1", "N"),
    ]

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
    diagram = ET.SubElement(mxfile, "diagram", {"id": "food-sewa-er", "name": "FOOD SEWA ER Diagram"})
    model = ET.SubElement(
        diagram,
        "mxGraphModel",
        {
            "dx": "1500",
            "dy": "1000",
            "grid": "1",
            "gridSize": "10",
            "guides": "1",
            "tooltips": "1",
            "connect": "1",
            "arrows": "1",
            "fold": "1",
            "page": "1",
            "pageScale": "1",
            "pageWidth": "2200",
            "pageHeight": "1750",
            "math": "0",
            "shadow": "0",
        },
    )
    root = ET.SubElement(model, "root")
    ET.SubElement(root, "mxCell", {"id": "0"})
    ET.SubElement(root, "mxCell", {"id": "1", "parent": "0"})

    add_vertex(
        root,
        "title",
        "<b>FOOD SEWA ER Diagram</b><br>Chen-style ER diagram using diagrams.net Entity Relation shapes",
        "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=top;whiteSpace=wrap;fontSize=22;fontColor=#16233a;",
        60,
        35,
        780,
        55,
    )

    entity_width = 160
    entity_height = 70
    for table_name, (x, y) in entity_positions.items():
        add_vertex(root, f"entity_{table_name}", html.escape(table_name), ENTITY_STYLE, x, y, entity_width, entity_height)

    for table_name, attrs in display_attributes.items():
        x, y = entity_positions[table_name]
        # Keep attributes inside each entity's visual block. This avoids
        # collisions with neighbouring entities and relationship diamonds.
        offsets = [(-45, 92), (95, 92), (-45, 144), (95, 144)]
        for idx, attr in enumerate(attrs[:4]):
            dx, dy = offsets[idx]
            attr_id = f"attr_{table_name}_{idx}"
            normalized_attr = attr.replace("/", "_")
            style = KEY_ATTRIBUTE_STYLE if attr == "id" else FK_ATTRIBUTE_STYLE if normalized_attr in fk_columns[table_name] else ATTRIBUTE_STYLE
            add_vertex(root, attr_id, html.escape(attr), style, x + dx, y + dy, 120, 40)
            add_edge(root, f"edge_attr_{table_name}_{idx}", f"entity_{table_name}", attr_id)

    for idx, (left, right, label, left_card, right_card) in enumerate(relationships, start=1):
        rel_x = 1740 + ((idx - 1) % 2) * 170
        rel_y = 140 + ((idx - 1) // 2) * 132
        rel_id = f"rel_{idx}_{left}_{right}"
        add_vertex(root, rel_id, html.escape(label), RELATIONSHIP_STYLE, rel_x, rel_y, 105, 60)
        add_edge(root, f"edge_{idx}_left", f"entity_{left}", rel_id, html.escape(left_card))
        add_edge(root, f"edge_{idx}_right", rel_id, f"entity_{right}", html.escape(right_card))

    add_vertex(
        root,
        "legend",
        (
            "<b>Legend</b><br>"
            "Rectangle = Entity, Oval = Attribute, Diamond = Relationship<br>"
            "Underlined yellow attributes are primary keys. Blue attributes are foreign keys. "
            "Line labels show cardinality."
        ),
        "rounded=1;whiteSpace=wrap;html=1;arcSize=8;spacing=10;fillColor=#f8fafc;strokeColor=#c8d2dd;fontSize=12;fontColor=#314255;",
        60,
        1620,
        2080,
        80,
    )

    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    ET.indent(mxfile, space="  ")
    OUTPUT_PATH.write_text(ET.tostring(mxfile, encoding="unicode", xml_declaration=True), encoding="utf-8")
    print(f"Created {OUTPUT_PATH}")


if __name__ == "__main__":
    main()
