from __future__ import annotations

from pathlib import Path
from xml.etree import ElementTree as ET


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_PATH = ROOT / "assets" / "diagrams" / "food_sewa_er_diagram.drawio"


ENTITY_STYLE = (
    "rounded=0;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;"
    "fontColor=#111111;fontStyle=1;fontSize=13;shadow=0;"
)
ATTRIBUTE_STYLE = (
    "ellipse;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;"
    "fontColor=#111111;fontSize=11;shadow=0;"
)
KEY_ATTRIBUTE_STYLE = (
    "ellipse;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;"
    "fontColor=#111111;fontSize=11;fontStyle=4;shadow=0;"
)
FK_ATTRIBUTE_STYLE = (
    "ellipse;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;"
    "fontColor=#111111;fontSize=11;shadow=0;"
)
DERIVED_ATTRIBUTE_STYLE = (
    "ellipse;shape=doubleEllipse;whiteSpace=wrap;html=1;fillColor=#f8cecc;"
    "strokeColor=#b85450;fontColor=#111111;fontSize=11;dashed=1;shadow=0;"
)
RELATIONSHIP_STYLE = (
    "rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;"
    "fontColor=#111111;fontStyle=1;fontSize=11;shadow=0;"
)
EDGE_STYLE = "endArrow=none;html=1;rounded=0;strokeColor=#eeeeee;fontColor=#eeeeee;fontSize=10;"


ENTITIES = {
    "users": ["id", "full_name", "email", "phone", "status"],
    "admins": ["id", "name", "email", "status", "created_at"],
    "addresses": ["id", "user_id", "phone", "address_name", "lat/lng"],
    "categories": ["id", "name", "status", "created_at"],
    "foods": ["id", "category_id", "name", "price", "stock_qty"],
    "carts": ["id", "user_id", "session_id", "created_at"],
    "cart_items": ["id", "cart_id", "food_id", "qty", "price_snapshot"],
    "wishlists": ["id", "user_id", "session_id", "created_at"],
    "wishlist_items": ["id", "wishlist_id", "food_id", "created_at"],
    "orders": ["id", "user_id", "address_id", "order_code", "total_amount", "coupon_id"],
    "order_items": ["id", "order_id", "food_id", "name_snapshot", "qty", "price_snapshot"],
    "payments": ["id", "order_id", "gateway", "amount", "status"],
    "coupons": ["id", "code", "user_id", "discount_percent", "type", "expires_at"],
    "coupon_usage": ["id", "coupon_id", "user_id", "order_id", "used_at"],
    "system_settings": ["id", "setting_key", "setting_value", "updated_at"],
}

FK_ATTRIBUTES = {
    "addresses": {"user_id"},
    "foods": {"category_id"},
    "carts": {"user_id"},
    "cart_items": {"cart_id", "food_id"},
    "wishlists": {"user_id"},
    "wishlist_items": {"wishlist_id", "food_id"},
    "orders": {"user_id", "address_id", "coupon_id"},
    "order_items": {"order_id", "food_id"},
    "payments": {"order_id"},
    "coupons": {"user_id"},
    "coupon_usage": {"coupon_id", "user_id", "order_id"},
}

RELATIONSHIPS = [
    ("users", "addresses", "HAS", "1", "N"),
    ("users", "carts", "OWNS", "1", "0..1"),
    ("users", "wishlists", "OWNS", "1", "0..1"),
    ("users", "orders", "PLACES", "1", "N"),
    ("users", "coupons", "RECEIVES", "1", "N"),
    ("users", "coupon_usage", "USES", "1", "N"),
    ("categories", "foods", "GROUPS", "1", "N"),
    ("foods", "cart_items", "SELECTED_IN", "1", "N"),
    ("foods", "wishlist_items", "SAVED_IN", "1", "N"),
    ("foods", "order_items", "ORDERED_AS", "1", "N"),
    ("carts", "cart_items", "CONTAINS", "1", "N"),
    ("wishlists", "wishlist_items", "CONTAINS", "1", "N"),
    ("addresses", "orders", "SHIPS_TO", "1", "N"),
    ("orders", "order_items", "CONTAINS", "1", "N"),
    ("orders", "payments", "PAID_BY", "1", "N"),
    ("orders", "coupon_usage", "RECORDS", "1", "N"),
    ("coupons", "orders", "APPLIED_TO", "0..1", "N"),
    ("coupons", "coupon_usage", "LOGGED_BY", "1", "N"),
]


def make_cell(parent: ET.Element, cell_id: str, attrs: dict[str, str]) -> ET.Element:
    return ET.SubElement(parent, "mxCell", {"id": cell_id, **attrs})


def add_geometry(parent: ET.Element, x: int, y: int, width: int, height: int) -> None:
    ET.SubElement(
        parent,
        "mxGeometry",
        {"x": str(x), "y": str(y), "width": str(width), "height": str(height), "as": "geometry"},
    )


def add_vertex(root: ET.Element, cell_id: str, value: str, style: str, x: int, y: int, w: int, h: int) -> None:
    cell = make_cell(root, cell_id, {"value": value, "style": style, "vertex": "1", "parent": "1"})
    add_geometry(cell, x, y, w, h)


def add_edge(root: ET.Element, cell_id: str, source: str, target: str, value: str = "") -> None:
    cell = make_cell(
        root,
        cell_id,
        {
            "value": value,
            "style": EDGE_STYLE,
            "edge": "1",
            "parent": "1",
            "source": source,
            "target": target,
        },
    )
    ET.SubElement(cell, "mxGeometry", {"relative": "1", "as": "geometry"})


def attr_style(entity: str, attr: str) -> str:
    if attr == "id":
        return KEY_ATTRIBUTE_STYLE
    if attr == "total_amount":
        return DERIVED_ATTRIBUTE_STYLE
    if attr in FK_ATTRIBUTES.get(entity, set()):
        return FK_ATTRIBUTE_STYLE
    return ATTRIBUTE_STYLE


def main() -> None:
    entity_positions: dict[str, tuple[int, int]] = {}
    ordered_entities = list(ENTITIES)
    start_x, start_y = 70, 130
    col_step, row_step = 480, 540
    for idx, entity in enumerate(ordered_entities):
        col = idx % 5
        row = idx // 5
        entity_positions[entity] = (start_x + col * col_step, start_y + row * row_step)

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
    diagram = ET.SubElement(mxfile, "diagram", {"id": "food-sewa-all-entities-er", "name": "FOOD SEWA ER Diagram"})
    model = ET.SubElement(
        diagram,
        "mxGraphModel",
        {
            "dx": "1800",
            "dy": "1050",
            "grid": "1",
            "gridSize": "10",
            "guides": "1",
            "tooltips": "1",
            "connect": "1",
            "arrows": "1",
            "fold": "1",
            "page": "1",
            "pageScale": "1",
            "pageWidth": "2920",
            "pageHeight": "1820",
            "background": "#000000",
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
        "FOOD SEWA ER DIAGRAM",
        "text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=top;whiteSpace=wrap;fontSize=28;fontStyle=1;fontColor=#eeeeee;",
        60,
        32,
        520,
        50,
    )

    attr_offsets = [
        (150, 0),
        (0, 95),
        (285, 95),
        (0, 275),
        (285, 275),
        (145, 350),
    ]

    for entity, attrs in ENTITIES.items():
        group_x, group_y = entity_positions[entity]
        entity_id = f"entity_{entity}"
        add_vertex(root, entity_id, entity.upper(), ENTITY_STYLE, group_x + 125, group_y + 170, 165, 74)
        for idx, attr in enumerate(attrs):
            ax, ay = attr_offsets[idx]
            attr_id = f"attr_{entity}_{idx}"
            add_vertex(root, attr_id, attr, attr_style(entity, attr), group_x + ax, group_y + ay, 130, 52)
            add_edge(root, f"edge_{entity}_{idx}", entity_id, attr_id)

    rel_x_positions = [2470, 2660]
    for idx, (left, right, label, left_card, right_card) in enumerate(RELATIONSHIPS):
        rel_x = rel_x_positions[idx % 2]
        rel_y = 120 + (idx // 2) * 150
        rel_id = f"rel_{idx}_{label.lower()}"
        add_vertex(root, rel_id, label, RELATIONSHIP_STYLE, rel_x, rel_y, 130, 82)
        add_edge(root, f"edge_rel_{idx}_left", f"entity_{left}", rel_id, left_card)
        add_edge(root, f"edge_rel_{idx}_right", rel_id, f"entity_{right}", right_card)

    add_vertex(
        root,
        "legend",
        (
            "Legend: Rectangle = Entity | Oval = Attribute | Diamond = Relationship | "
            "Underlined oval = Primary Key | Blue oval = Foreign Key | Dashed pink oval = Derived value"
        ),
        "rounded=1;whiteSpace=wrap;html=1;arcSize=8;spacing=10;fillColor=#222222;strokeColor=#eeeeee;fontSize=13;fontColor=#eeeeee;",
        60,
        1700,
        2700,
        65,
    )

    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    ET.indent(mxfile, space="  ")
    OUTPUT_PATH.write_text(ET.tostring(mxfile, encoding="unicode", xml_declaration=True), encoding="utf-8")
    print(f"Created {OUTPUT_PATH}")


if __name__ == "__main__":
    main()
