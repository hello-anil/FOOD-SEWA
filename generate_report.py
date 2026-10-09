#!/usr/bin/env python3
"""
Generate FOOD SEWA Final Project Report (TU BCA Format)
"""

from docx import Document
from docx.shared import Pt, Inches, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
import copy

doc = Document()

# ─── PAGE SETUP ───────────────────────────────────────────────────────────────
section = doc.sections[0]
section.page_width  = Inches(8.27)   # A4
section.page_height = Inches(11.69)
section.top_margin    = Inches(1.0)
section.bottom_margin = Inches(1.0)
section.right_margin  = Inches(1.0)
section.left_margin   = Inches(1.25)

# ─── STYLES ───────────────────────────────────────────────────────────────────
def apply_body_style(para, size=12, bold=False, italic=False, align=WD_ALIGN_PARAGRAPH.JUSTIFY):
    para.alignment = align
    para.paragraph_format.space_before = Pt(0)
    para.paragraph_format.space_after  = Pt(6)
    para.paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    for run in para.runs:
        run.font.name = 'Times New Roman'
        run.font.size = Pt(size)
        run.font.bold   = bold
        run.font.italic = italic

def set_run_font(run, size=12, bold=False, italic=False):
    run.font.name = 'Times New Roman'
    run.font.size = Pt(size)
    run.font.bold   = bold
    run.font.italic = italic

def add_body(text, size=12, bold=False, italic=False, align=WD_ALIGN_PARAGRAPH.JUSTIFY):
    p = doc.add_paragraph()
    run = p.add_run(text)
    set_run_font(run, size, bold, italic)
    p.alignment = align
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after  = Pt(6)
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    return p

def add_chapter_heading(text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(12)
    p.paragraph_format.space_after  = Pt(12)
    run = p.add_run(text)
    set_run_font(run, 16, bold=True)
    return p

def add_section_heading(text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(10)
    p.paragraph_format.space_after  = Pt(6)
    run = p.add_run(text)
    set_run_font(run, 14, bold=True)
    return p

def add_subsection_heading(text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after  = Pt(4)
    run = p.add_run(text)
    set_run_font(run, 12, bold=True)
    return p

def add_figure_caption(text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after  = Pt(10)
    run = p.add_run(text)
    set_run_font(run, 12, bold=True, italic=True)
    return p

def add_table_caption(text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(10)
    p.paragraph_format.space_after  = Pt(4)
    run = p.add_run(text)
    set_run_font(run, 12, bold=True)
    return p

def add_image_placeholder(caption):
    """Add a placeholder box where an image will be inserted."""
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run('[IMAGE PLACEHOLDER: ' + caption + ']')
    set_run_font(run, 11, italic=True)
    run.font.color.rgb = RGBColor(0x88, 0x88, 0x88)
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after  = Pt(4)
    return p

def add_code_block(lines):
    for line in lines:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after  = Pt(0)
        p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
        run = p.add_run(line)
        run.font.name = 'Courier New'
        run.font.size = Pt(9)

def add_page_break():
    doc.add_page_break()

def add_table(headers, rows, col_widths=None):
    """Add a table with headers and rows."""
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.style = 'Table Grid'
    table.alignment = WD_TABLE_ALIGNMENT.CENTER

    # Header row
    hdr_row = table.rows[0]
    for i, h in enumerate(headers):
        cell = hdr_row.cells[i]
        cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = p.add_run(h)
        set_run_font(run, 11, bold=True)
        # Shade header
        tc = cell._tc
        tcPr = tc.get_or_add_tcPr()
        shd = OxmlElement('w:shd')
        shd.set(qn('w:fill'), 'D9E1F2')
        shd.set(qn('w:color'), 'auto')
        shd.set(qn('w:val'), 'clear')
        tcPr.append(shd)

    # Data rows
    for r_idx, row_data in enumerate(rows):
        row = table.rows[r_idx + 1]
        for c_idx, cell_data in enumerate(row_data):
            cell = row.cells[c_idx]
            p = cell.paragraphs[0]
            p.alignment = WD_ALIGN_PARAGRAPH.LEFT
            run = p.add_run(str(cell_data))
            set_run_font(run, 10)

    if col_widths:
        for row in table.rows:
            for i, w in enumerate(col_widths):
                row.cells[i].width = Inches(w)
    return table


def add_roman_page_number():
    """Add Roman numeral page numbering to current section (bottom center)."""
    pass  # Handled at section level in Word

def add_bullet(text, level=0):
    p = doc.add_paragraph(style='List Bullet')
    run = p.add_run(text)
    set_run_font(run, 12)
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    p.paragraph_format.space_after = Pt(3)
    return p

def add_numbered(text):
    p = doc.add_paragraph(style='List Number')
    run = p.add_run(text)
    set_run_font(run, 12)
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    p.paragraph_format.space_after = Pt(3)
    return p

# ════════════════════════════════════════════════════════════════════════════════
# COVER PAGE
# ════════════════════════════════════════════════════════════════════════════════

add_image_placeholder('Tribhuvan University Logo')

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(18)
p.paragraph_format.space_after  = Pt(4)
r = p.add_run('Tribhuvan University')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(0)
p.paragraph_format.space_after  = Pt(24)
r = p.add_run('Faculty of Humanities and Social Sciences')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(24)
p.paragraph_format.space_after  = Pt(4)
r = p.add_run('A PROJECT REPORT ON')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(4)
p.paragraph_format.space_after  = Pt(28)
r = p.add_run('FOOD SEWA')
set_run_font(r, 16, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(0)
p.paragraph_format.space_after  = Pt(4)
r = p.add_run('Submitted to')
set_run_font(r, 12, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(4)
r = p.add_run('Department of Computer Application')
set_run_font(r, 12, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(16)
r = p.add_run('Samriddhi College')
set_run_font(r, 12, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(24)
r = p.add_run('In partial fulfillment of the requirements for the Bachelors in Computer Application')
set_run_font(r, 12, italic=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(4)
r = p.add_run('Submitted by')
set_run_font(r, 12)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(4)
r = p.add_run('Anil Prajapati [111302128]')
set_run_font(r, 12)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(24)
r = p.add_run('Rohit Dangol [111302142]')
set_run_font(r, 12)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(4)
r = p.add_run('Under the Supervision of')
set_run_font(r, 12)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(4)
r = p.add_run('Mr. Abishek Shrestha')
set_run_font(r, 12, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(0)
r = p.add_run('April, 2026')
set_run_font(r, 12)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# SUPERVISOR'S RECOMMENDATION  (page ii)
# ════════════════════════════════════════════════════════════════════════════════
add_image_placeholder('Tribhuvan University Logo')

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(12)
p.paragraph_format.space_after  = Pt(4)
r = p.add_run('Tribhuvan University')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(4)
r = p.add_run('Faculty of Humanities and Social Sciences')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(24)
r = p.add_run('Samriddhi College')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(24)
p.paragraph_format.space_after  = Pt(24)
r = p.add_run("Supervisor's Recommendation")
set_run_font(r, 14, bold=True)

add_body(
    "I hereby recommend that this project prepared under my supervision by Anil Prajapati "
    "and Rohit Dangol entitled “FOOD SEWA” in partial fulfillment of the requirements for the "
    "degree of Bachelor of Computer Application is recommended for the final evaluation."
)

doc.add_paragraph('')
doc.add_paragraph('')
doc.add_paragraph('')

add_body('SIGNATURE', bold=True)
add_body('Mr. Abishek Shrestha')
add_body('SUPERVISOR', bold=True)
add_body('Program Coordinator, BCA')
add_body('Samriddhi College')
add_body('Lokanthali-16, Bhaktapur')

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# LETTER OF APPROVAL  (page iii)
# ════════════════════════════════════════════════════════════════════════════════
add_image_placeholder('Tribhuvan University Logo')

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(12)
p.paragraph_format.space_after  = Pt(4)
r = p.add_run('Tribhuvan University')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(4)
r = p.add_run('Faculty of Humanities and Social Sciences')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(24)
r = p.add_run('Samriddhi College')
set_run_font(r, 14, bold=True)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(18)
p.paragraph_format.space_after  = Pt(18)
r = p.add_run('LETTER OF APPROVAL')
set_run_font(r, 14, bold=True)

add_body(
    "This is to certify that this project prepared by ANIL PRAJAPATI and ROHIT DANGOL "
    "entitled “FOOD SEWA” in partial fulfillment of the requirements for the degree of "
    "Bachelor in Computer Application has been evaluated. In our opinion it is satisfactory in "
    "the scope and quality as a project for the required degree."
)

doc.add_paragraph('')

# Approval table
approval_table = doc.add_table(rows=2, cols=2)
approval_table.style = 'Table Grid'
approval_table.alignment = WD_TABLE_ALIGNMENT.CENTER

cells = [
    ("Supervisor\n\nMr. Abishek Shrestha\nProgram Coordinator, BCA\nSamriddhi College\nLokanthali-16, Bhaktapur",
     "HOD/Coordinator\n\nMr. Jeeban Dhungel\nCoordinator\nSamriddhi College\nLokanthali-16, Bhaktapur"),
    ("___________________________\nInternal Examiner",
     "___________________________\nExternal Examiner"),
]
for r_idx, (c1, c2) in enumerate(cells):
    row = approval_table.rows[r_idx]
    for c_idx, text in enumerate([c1, c2]):
        cell = row.cells[c_idx]
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(6)
        p.paragraph_format.space_after = Pt(6)
        run = p.add_run(text)
        set_run_font(run, 11)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# ABSTRACT  (page iv)
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Abstract')

add_body(
    "The FOOD SEWA project addresses the growing demand for efficient online food ordering "
    "and delivery services in urban and semi-urban areas of Nepal. With the rapid increase in "
    "smartphone penetration and internet accessibility, traditional food ordering methods are "
    "becoming obsolete. This project develops a comprehensive web-based platform that "
    "connects customers and the restaurant management in a seamless ecosystem, enabling "
    "real-time order tracking and multi-gateway payment processing."
)

add_body(
    "The system is built using PHP 8 for server-side scripting and MySQL 8 for robust "
    "database management, ensuring scalability, reliability, and real-time order tracking. "
    "Key features include food category management, menu browsing with wishlist support, "
    "session-aware cart management (supporting both guest and authenticated users), multi-address "
    "delivery management, coupon-based discount engine, and integration with three payment "
    "gateways: Stripe, eSewa, and Khalti. The platform also incorporates a unique late-delivery "
    "compensation mechanism that automatically generates a private discount coupon when an "
    "order exceeds its computed delivery time threshold."
)

add_body(
    "Delivery route estimation uses a Haversine distance formula combined with an A* pathfinding "
    "algorithm over OSRM-provided road geometry, enabling accurate expected delivery time "
    "calculation. Real-time rider position is interpolated along the computed route path based on "
    "elapsed delivery time. System testing validated performance under concurrent user loads "
    "and ensured data integrity across all transactional operations using MySQL atomic transactions "
    "with row-level locking. The responsive web interface, built with Bootstrap 5, ensures "
    "compatibility across desktop and mobile devices."
)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
p.paragraph_format.space_before = Pt(6)
p.paragraph_format.space_after  = Pt(6)
p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
run_bold = p.add_run('Keywords: ')
set_run_font(run_bold, 12, bold=True)
run_normal = p.add_run('Food Delivery, Online Ordering, PHP, MySQL, Real-time Tracking, Payment Gateway, Coupon Engine, A* Algorithm, Role-based Access Control.')
set_run_font(run_normal, 12)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# ACKNOWLEDGEMENT  (page v)
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Acknowledgement')

add_body(
    "We express our profound gratitude to Mr. Abishek Shrestha, our project supervisor and "
    "Program Coordinator of BCA at Samriddhi College, for his unwavering guidance, insightful "
    "feedback, and mentorship throughout this endeavor. His technical expertise was pivotal in "
    "shaping the project's direction and ensuring its successful completion."
)

add_body(
    "We extend our sincere thanks to the Department of Computer Application at Samriddhi "
    "College, Tribhuvan University, for providing the academic framework, resources, and "
    "infrastructure essential to this project. Special acknowledgment goes to Mr. Jeeban Dhungel, "
    "Coordinator, for his administrative support and to our peers and faculty members for their "
    "constructive critiques during presentations and reviews."
)

add_body(
    "Our heartfelt appreciation also goes to our families and friends for their constant "
    "encouragement, patience, and support, which kept us motivated during challenging phases of "
    "development, particularly during the implementation of the real-time tracking and payment "
    "integration modules."
)

add_body(
    "Finally, we acknowledge the contributions of the open-source community—particularly "
    "the developers of PHP, MySQL, Bootstrap, OSRM, and Stripe SDK—whose tools and "
    "documentation greatly facilitated the design and implementation of FOOD SEWA. This project "
    "stands as a collaborative effort, and we are deeply grateful to everyone who contributed "
    "to its realization."
)

doc.add_paragraph('')
add_body('Anil Prajapati', bold=True)
add_body('Rohit Dangol', bold=True)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# TABLE OF CONTENTS  (page vi)
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Table of Contents')

toc_entries = [
    ("Supervisor’s Recommendation", "ii"),
    ("Letter of Approval", "iii"),
    ("Abstract", "iv"),
    ("Acknowledgement", "v"),
    ("Table of Contents", "vi"),
    ("List of Abbreviations", "vii"),
    ("List of Figures", "viii"),
    ("List of Tables", "ix"),
    ("Chapter 1: Introduction", "1"),
    ("    1.1 Introduction", "1"),
    ("    1.2 Problem Statement", "2"),
    ("    1.3 Objectives", "3"),
    ("    1.4 Scope and Limitation", "3"),
    ("    1.5 Development Methodology", "4"),
    ("    1.6 Report Organization", "5"),
    ("Chapter 2: Background Study and Literature Review", "6"),
    ("    2.1 Background Study", "6"),
    ("    2.2 Literature Review", "7"),
    ("Chapter 3: System Analysis and Design", "10"),
    ("    3.1 System Analysis", "10"),
    ("        3.1.1 Requirement Analysis", "10"),
    ("        3.1.2 Feasibility Analysis", "13"),
    ("        3.1.3 Data Modelling", "16"),
    ("        3.1.4 Process Modelling", "17"),
    ("    3.2 System Design", "19"),
    ("        3.2.1 Architectural Design", "19"),
    ("        3.2.2 Database Schema Design", "20"),
    ("        3.2.3 Interface Design (UI/UX)", "21"),
    ("        3.2.4 Physical DFD", "23"),
    ("    3.3 Algorithm Details", "23"),
    ("Chapter 4: Implementation and Testing", "26"),
    ("    4.1 Implementation", "26"),
    ("        4.1.1 Tools Used", "26"),
    ("        4.1.2 Implementation Details of Modules", "27"),
    ("    4.2 Testing", "31"),
    ("        4.2.1 Test Cases for Unit Testing", "31"),
    ("        4.2.2 Test Cases for System Testing", "33"),
    ("Chapter 5: Conclusion and Future Recommendations", "35"),
    ("    5.1 Conclusion", "35"),
    ("    5.2 Lessons Learnt / Outcome", "36"),
    ("    5.3 Future Recommendations", "37"),
    ("References", "38"),
    ("Bibliography", "39"),
    ("Appendices", "40"),
    ("    Appendix A: Source Code Snippets", "40"),
    ("    Appendix B: Additional Screenshots", "44"),
    ("    Appendix C: Supervisor’s Visit Log Sheet", "45"),
]

for entry, pg in toc_entries:
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after  = Pt(2)
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
    is_chapter = entry.startswith('Chapter') or entry in [
        "Supervisor’s Recommendation","Letter of Approval","Abstract",
        "Acknowledgement","Table of Contents","List of Abbreviations","List of Figures",
        "List of Tables","References","Bibliography","Appendices"
    ]
    run = p.add_run(entry)
    set_run_font(run, 12, bold=is_chapter)
    # Tab stop and page number
    tab_run = p.add_run('\t' + pg)
    set_run_font(tab_run, 12, bold=is_chapter)
    p.paragraph_format.tab_stops.add_tab_stop(Inches(5.8), WD_ALIGN_PARAGRAPH.RIGHT)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# LIST OF ABBREVIATIONS  (page vii)
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('List of Abbreviations')

abbrev_headers = ['Abbreviation', 'Full Form']
abbrev_rows = [
    ('API',    'Application Programming Interface'),
    ('BCA',    'Bachelor of Computer Application'),
    ('CSRF',   'Cross-Site Request Forgery'),
    ('CSS',    'Cascading Style Sheets'),
    ('CRUD',   'Create, Read, Update, Delete'),
    ('DFD',    'Data Flow Diagram'),
    ('ER',     'Entity-Relationship'),
    ('ETA',    'Estimated Time of Arrival'),
    ('FR',     'Functional Requirements'),
    ('HMAC',   'Hash-based Message Authentication Code'),
    ('HTML',   'Hypertext Markup Language'),
    ('HTTP',   'Hypertext Transfer Protocol'),
    ('LAMP',   'Linux, Apache, MySQL, PHP'),
    ('MySQL',  'My Structured Query Language'),
    ('NFR',    'Non-Functional Requirements'),
    ('NPR',    'Nepalese Rupee'),
    ('OSRM',   'Open Source Routing Machine'),
    ('OTP',    'One-Time Password'),
    ('PHP',    'Hypertext Preprocessor'),
    ('SDLC',   'Software Development Life Cycle'),
    ('SQL',    'Structured Query Language'),
    ('SSL',    'Secure Sockets Layer'),
    ('TU',     'Tribhuvan University'),
    ('UI/UX',  'User Interface / User Experience'),
    ('UML',    'Unified Modeling Language'),
    ('URL',    'Uniform Resource Locator'),
]
add_table(abbrev_headers, abbrev_rows, col_widths=[1.5, 4.5])
add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# LIST OF FIGURES  (page viii)
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('List of Figures')

figures = [
    ('Figure 1',  'Gantt Chart Illustrating the Project Development Timeline'),
    ('Figure 2',  'E-R Diagram Showing Relationship Between Users and Orders'),
    ('Figure 3',  'E-R Diagram Showing Users, Orders, and Order_Items'),
    ('Figure 4',  'Level 0 DFD of FOOD SEWA'),
    ('Figure 5',  'Level 1 DFD of FOOD SEWA'),
    ('Figure 6',  'Use Case Diagram Depicting User Interactions in FOOD SEWA'),
    ('Figure 7',  'Architectural Design Diagram of FOOD SEWA (3-Tier Client-Server Model)'),
    ('Figure 8',  'Database Schema Diagram Showing Tables and Foreign Key Relationships'),
    ('Figure 9',  'Home Page Interface Design'),
    ('Figure 10', 'User Login / Registration Page Interface Design'),
    ('Figure 11', 'Admin Login Page Interface Design'),
    ('Figure 12', 'Browse Foods Page Interface Design'),
    ('Figure 13', 'Checkout Page Interface Design'),
    ('Figure 14', 'Real-time Order Tracking Page Interface Design'),
    ('Figure 15', 'Admin Dashboard Interface Design'),
    ('Figure 16', 'Physical DFD of FOOD SEWA'),
]
for fig, cap in figures:
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after  = Pt(2)
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
    run = p.add_run(f'{fig}: {cap}')
    set_run_font(run, 12)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# LIST OF TABLES  (page ix)
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('List of Tables')

tables_list = [
    ('Table 1',  'List of Abbreviations'),
    ('Table 2',  'Comparison of Existing Food Delivery Platforms'),
    ('Table 3',  'Functional Requirements'),
    ('Table 4',  'Non-Functional Requirements'),
    ('Table 5',  'Use Case Description: Place Order'),
    ('Table 6',  'Use Case Description: Manage Menu'),
    ('Table 7',  'Use Case Description: Process Payment'),
    ('Table 8',  'Tools and Technologies Used'),
    ('Table 9',  'Test Cases for Unit Testing'),
    ('Table 10', 'Test Cases for System Testing'),
    ('Table 11', 'Supervisor\'s Visit Log Sheet'),
]
for tbl, cap in tables_list:
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after  = Pt(2)
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
    run = p.add_run(f'{tbl}: {cap}')
    set_run_font(run, 12)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# CHAPTER 1: INTRODUCTION
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Chapter 1: Introduction')

add_section_heading('1.1 Introduction')
add_body(
    "FOOD SEWA is a web-based food ordering and delivery management platform designed to "
    "meet the growing demand for fast, convenient, and reliable food ordering in today’s "
    "digital world. With the rapid increase in smartphone penetration and internet usage in "
    "Nepal, customers increasingly prefer ordering food online rather than using traditional "
    "methods such as telephone calls or physical visits to restaurants, which often lead to "
    "delays, order errors, and limited menu visibility [1]. This platform provides customers "
    "with a seamless interface to browse food categories, manage a personal cart (supporting "
    "both guest and authenticated sessions), apply discount coupons, select from multiple saved "
    "delivery addresses, and pay securely through Stripe, eSewa, or Khalti payment gateways."
)
add_body(
    "The platform is built using PHP 8 for server-side scripting and MySQL 8 as the relational "
    "database management system, running on a LAMP stack (Linux, Apache, MySQL, PHP). The "
    "frontend is implemented with HTML5, CSS3, JavaScript, and Bootstrap 5 to ensure "
    "responsiveness across all device types. A distinguishing feature of FOOD SEWA is its "
    "real-time order tracking system, which uses a Haversine distance computation combined "
    "with an A* pathfinding algorithm over road geometry provided by the OSRM (Open Source "
    "Routing Machine) service to estimate accurate delivery times and interpolate the "
    "delivery rider’s position along the route path."
)
add_body(
    "The system also features a late-delivery compensation mechanism: if a delivery exceeds "
    "its computed time threshold, the platform automatically generates a private percentage "
    "discount coupon for the affected customer. The administrative panel provides the restaurant "
    "operator with complete control over food categories, menu items, stock quantities, coupon "
    "management, delivery status progression, payment oversight, and system settings. Security "
    "measures include bcrypt password hashing, CSRF token validation, rate limiting, session "
    "idle and absolute timeout enforcement, Content Security Policy headers, and parameterized "
    "SQL queries throughout [2]."
)

add_section_heading('1.2 Problem Statement')
add_body(
    "The traditional food ordering system in Nepal faces numerous challenges that significantly "
    "affect both customers and restaurant operators. Customers frequently encounter problems "
    "such as limited menu visibility, inability to compare options, long waiting times due to "
    "manual order processing, order inaccuracies arising from verbal or telephone communication, "
    "and reliance on cash-only payment methods. Many small and medium-sized restaurants in "
    "semi-urban areas lack any form of digital presence, restricting their customer reach and "
    "growth opportunities [2]."
)
add_body(
    "Existing food delivery platforms operating in Nepal, such as Foodmandu and Pathao Food, "
    "while effective for large urban centres, impose high commission rates (typically 20–30%), "
    "offer limited customisation for individual restaurant operators, and lack transparent "
    "real-time delivery tracking for customers [4][5]. Furthermore, these platforms do not "
    "provide mechanisms to compensate customers for delivery delays, nor do they offer "
    "locally-aware route estimation based on actual road distances."
)
add_body(
    "FOOD SEWA addresses this gap by providing an affordable, self-hosted, and fully "
    "integrated platform that eliminates high commission dependencies, incorporates "
    "GPS-aware delivery time estimation using the Haversine formula and A* algorithm, "
    "and includes an automated late-delivery coupon compensation system. The platform "
    "supports three payment gateways (Stripe, eSewa, Khalti) to accommodate local "
    "and international payment preferences, and ensures robust data integrity through "
    "MySQL atomic transactions with row-level locking during stock deduction and "
    "payment verification."
)

add_section_heading('1.3 Objectives')
add_body("The specific objectives of the FOOD SEWA platform are:")
add_bullet("To design and implement a responsive web-based food ordering system enabling customers to browse menus, manage carts, apply discount coupons, and complete secure payments via Stripe, eSewa, or Khalti.")
add_bullet("To implement a real-time order tracking system using GPS coordinates, Haversine distance computation, A* pathfinding over OSRM road geometry, and time-based rider position interpolation.")
add_bullet("To develop an automated late-delivery compensation mechanism that evaluates delivery elapsed time against route-estimated thresholds and issues private discount coupons to affected customers.")
add_bullet("To provide the restaurant administrator with a comprehensive management panel covering food categories, menu items, stock control, coupon management, delivery status management, payment records, and system configuration.")
add_bullet("To implement robust application security through bcrypt password hashing, CSRF protection, rate limiting, parameterized queries, and strict session management.")

add_section_heading('1.4 Scope and Limitation')
add_subsection_heading('Scope')
add_body("The FOOD SEWA project encompasses the following functional areas:")
add_bullet("Customer module: user registration and login (with guest-to-user cart merging), food browsing by category, cart management, wishlist, multi-address management with GPS coordinates, checkout with coupon application, and multi-gateway payment.")
add_bullet("Order lifecycle management: automated order code and tracking number generation, delivery status progression (PENDING → CONFIRMED → OUT_FOR_DELIVERY → DELIVERED / CANCELLED), real-time tracking via OSRM and A* route computation.")
add_bullet("Late-delivery compensation: evaluation of delivery elapsed time against route-estimated limits, automated private coupon generation, customer-facing claim interface.")
add_bullet("Admin panel: food and category CRUD, coupon management (global and private types), order management, delivery management, payment oversight, user management, system settings.")
add_bullet("Security: CSRF token validation, bcrypt password hashing, rate limiting, SQL injection prevention via prepared statements, Content Security Policy, session timeout controls.")

add_subsection_heading('Limitations')
add_bullet("The platform currently serves a single restaurant. Multi-restaurant marketplace functionality is not implemented.")
add_bullet("No dedicated delivery agent mobile application; delivery management is performed through the admin panel.")
add_bullet("GPS-based rider tracking is simulated through time-based path interpolation; actual rider GPS updates are not implemented in this version.")
add_bullet("Native mobile applications for Android and iOS are not included; the platform is web-only.")
add_bullet("AI-based food recommendation engines are not included in this version.")
add_bullet("The system is limited to web-based access and requires an internet connection.")

add_section_heading('1.5 Development Methodology')
add_body(
    "The FOOD SEWA project was developed using the Incremental Development Model, a variant "
    "of iterative SDLC that builds and delivers the system in successive increments, each "
    "adding new functional capabilities while preserving and extending previously validated "
    "modules [7]. This methodology was chosen because the system’s modules (authentication, "
    "cart, checkout, payment, tracking, admin panel) have well-defined boundaries and could "
    "be developed, tested, and integrated independently."
)
add_body(
    "Each increment followed the sequence: requirements analysis → design → implementation "
    "→ unit testing → integration. The first increment established the database schema, "
    "configuration management, and user authentication. The second increment introduced food "
    "browsing, cart management (guest and authenticated), and wishlist functionality. The third "
    "increment implemented the checkout flow, order creation, and payment gateway integrations "
    "(Stripe, eSewa, Khalti). The fourth increment added real-time order tracking using the "
    "Haversine algorithm, A* path estimation, and OSRM routing. The fifth increment introduced "
    "the late-delivery coupon engine. The sixth increment completed the admin panel modules and "
    "system testing."
)
add_body(
    "The incremental approach provided flexibility to adapt requirements as implementation "
    "insights emerged, particularly during the development of the A* routing algorithm and "
    "transactional stock deduction logic. Version control was maintained using Git throughout "
    "the project lifecycle."
)

add_section_heading('1.6 Report Organization')
add_body("The report is organized into five main chapters as follows:")

add_body('Chapter 1: Introduction', bold=True)
add_body(
    "Introduces the project background, problem statement, objectives, scope and limitations, "
    "development methodology, and report organization."
)

add_body('Chapter 2: Background Study and Literature Review', bold=True)
add_body(
    "Reviews fundamental concepts of online food ordering systems, key enabling technologies, "
    "and analyzes existing platforms (Foodmandu, Pathao Food, Uber Eats, Grubhub, Zomato) "
    "to identify gaps addressed by FOOD SEWA."
)

add_body('Chapter 3: System Analysis and Design', bold=True)
add_body(
    "Details functional and non-functional requirements, feasibility analysis, ER data modelling, "
    "DFD process modelling, architectural design, database schema, interface design, and algorithm "
    "specifications for the coupon engine, payment verification, and A* routing."
)

add_body('Chapter 4: Implementation and Testing', bold=True)
add_body(
    "Describes the tools and technologies used, implementation details of each module based on "
    "the actual source code, and presents unit and system test cases with results."
)

add_body('Chapter 5: Conclusion and Future Recommendations', bold=True)
add_body(
    "Summarizes the project outcomes, key lessons learnt during development, and proposes "
    "future enhancements such as a native mobile application, multi-restaurant architecture, "
    "and AI-based recommendations."
)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# CHAPTER 2: BACKGROUND STUDY AND LITERATURE REVIEW
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Chapter 2: Background Study and Literature Review')

add_section_heading('2.1 Background Study')
add_body(
    "The food delivery industry has undergone a remarkable transformation driven by the "
    "convergence of digital technologies, widespread smartphone adoption, and improved "
    "internet connectivity. Online food ordering systems have become integral to modern "
    "urban lifestyles, offering consumers convenience, menu variety, and time efficiency [1]. "
    "The concept of food delivery is not novel; however, the digitisation of this service "
    "has fundamentally changed how restaurants operate and how customers interact with them."
)
add_body(
    "Traditional telephone-based ordering systems have been superseded by sophisticated web "
    "and mobile platforms offering real-time order tracking, multiple payment options, "
    "and data-driven customer insights. Key enabling technologies include server-side "
    "scripting frameworks (PHP, Node.js, Django), relational database systems (MySQL, "
    "PostgreSQL), geolocation APIs and routing engines (Google Maps, OSRM) for delivery "
    "optimisation, and payment gateway SDKs (Stripe, eSewa, Khalti) for secure financial "
    "transactions [7][8]."
)
add_body(
    "The COVID-19 pandemic accelerated the adoption of contactless food delivery services "
    "globally, and this shift has permanently altered consumer behaviour in Nepal. The Nepal "
    "Telecommunications Authority reported a steady increase in internet penetration, with "
    "mobile internet subscribers growing significantly, creating a viable market for web-based "
    "food ordering systems [9]. However, existing platforms in Nepal remain concentrated in "
    "Kathmandu Valley, leaving a significant portion of the population underserved. "
    "Furthermore, high commission fees charged by established platforms create financial "
    "barriers for small restaurants, motivating the development of a lower-cost, self-hosted "
    "alternative such as FOOD SEWA."
)

add_section_heading('2.2 Literature Review')
add_body(
    "A review of existing food delivery platforms reveals recurring strengths and weaknesses "
    "that informed the design of FOOD SEWA. The following analysis covers five major platforms:"
)

add_body(
    "Foodmandu [4] is Nepal’s pioneering online food delivery platform, operating "
    "primarily in Kathmandu Valley. It provides a wide range of restaurant options, "
    "cash-on-delivery and digital payment methods, and real-time order tracking. The "
    "platform has successfully localised services for Nepalese consumers. However, it "
    "charges commission rates of 20–30% from restaurants, has limited geographic coverage "
    "outside the Kathmandu Valley, and occasionally experiences delays during peak hours."
)

add_body(
    "Pathao Food [5] leverages Pathao’s established logistics network to provide food "
    "delivery across multiple cities in Nepal and Bangladesh. It offers competitive pricing, "
    "quick delivery times, and integration within the broader Pathao ride-hailing ecosystem. "
    "Limitations include lengthy restaurant onboarding processes and inconsistent service "
    "quality across different geographic locations."
)

add_body(
    "Uber Eats [6] is a global food delivery platform offering advanced features such as "
    "AI-based recommendations, detailed restaurant analytics, and sophisticated logistics "
    "optimisation. Despite its technological sophistication, Uber Eats has withdrawn from "
    "several Asian markets due to intense competition and profitability challenges. Its "
    "commission structure of 25–35% makes it less viable for small restaurants."
)

add_body(
    "Grubhub is one of the largest food delivery platforms in the United States, known for "
    "its extensive restaurant network and features such as the Grubhub+ membership programme "
    "and integrated point-of-sale systems. However, it faces criticism for high fees and "
    "challenges in maintaining delivery quality during high-demand periods."
)

add_body(
    "Zomato operates primarily in India and several other countries, offering food delivery "
    "alongside restaurant discovery and table reservation. Its live order tracking, multiple "
    "payment options, and robust review system are notable strengths. Challenges include "
    "delivery partner satisfaction issues and inconsistent service quality."
)

add_body("The following table summarises the comparative analysis of these platforms against FOOD SEWA:")

add_table_caption('Table 2: Comparison of Existing Food Delivery Platforms')
comparison_headers = ['Platform', 'Strengths', 'Weaknesses', 'Gap Addressed by FOOD SEWA']
comparison_rows = [
    ('Foodmandu', 'Local market knowledge, multiple payments', 'High commission (20-30%), limited coverage', 'Lower cost, late-delivery compensation'),
    ('Pathao Food', 'Logistics network, competitive pricing', 'Lengthy onboarding, inconsistent quality', 'Simplified restaurant ops, route-based ETA'),
    ('Uber Eats',  'AI recommendations, analytics', 'High commission (25-35%), market withdrawal', 'Affordable, locally adapted'),
    ('Grubhub',   'Large network, POS integration', 'High fees, delivery quality issues', 'Self-hosted, transparent pricing'),
    ('Zomato',    'Live tracking, review system', 'Inconsistent quality, profitability issues', 'A* route tracking, coupon compensation'),
    ('FOOD SEWA', 'Multi-gateway payment, A* tracking, auto late-coupon', 'Single restaurant, web-only', 'N/A – proposed solution'),
]
add_table(comparison_headers, comparison_rows, col_widths=[1.0, 1.6, 1.6, 1.8])

doc.add_paragraph('')
add_body(
    "Sharma and Gupta [1] conducted a comprehensive review of online food delivery systems "
    "and identified real-time order tracking, transparent pricing, and multiple payment options "
    "as the three most critical factors for customer satisfaction. Their findings directly "
    "influenced the feature prioritisation in FOOD SEWA. Adhikari [2] specifically studied "
    "e-commerce and food delivery platforms in Nepal and highlighted the need for locally-adapted "
    "solutions with lower commission structures and Nepali payment gateway integration. "
    "Thapa [3] identified digital transformation opportunities in Nepal’s food service "
    "industry, noting that route-based delivery estimation and late-delivery compensation "
    "were largely absent from existing local platforms."
)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# CHAPTER 3: SYSTEM ANALYSIS AND DESIGN
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Chapter 3: System Analysis and Design')

add_section_heading('3.1 System Analysis')
add_subsection_heading('3.1.1 Requirement Analysis')
add_body(
    "The requirements for FOOD SEWA were collected through analysis of the existing system "
    "gaps identified in the literature review, stakeholder consultations with potential "
    "restaurant operators and customers, and competitive analysis of existing Nepalese "
    "food delivery platforms. The analysis considered infrastructure requirements including "
    "server capacity, database performance, bandwidth requirements, and device compatibility."
)

add_subsection_heading('i. Functional Requirements')
add_body("The functional requirements define the core operations the system must support:")

add_table_caption('Table 3: Functional Requirements')
fr_headers = ['FR No.', 'Module', 'Description']
fr_rows = [
    ('FR-01', 'Authentication', 'Users shall register with full name, email, phone, and password (bcrypt hashed). Login with email and password. Rate limiting applied.'),
    ('FR-02', 'Cart',          'System shall support session-based guest carts and user-authenticated carts. Cart merges on login. Maximum 20 units per item enforced.'),
    ('FR-03', 'Wishlist',      'Users (guest or authenticated) shall toggle food items in a wishlist, persisted to database.'),
    ('FR-04', 'Food Browsing', 'System shall display all active food items, filterable by category, with name, description, price, stock, and image.'),
    ('FR-05', 'Addresses',     'Authenticated users shall manage multiple delivery addresses with text fields and optional GPS coordinates (lat/lng).'),
    ('FR-06', 'Coupon Engine', 'System shall validate coupon codes for: active status, expiry, usage limits, and user-specific restrictions. Discounts computed as percentage of subtotal.'),
    ('FR-07', 'Order Placement', 'Authenticated users shall place orders from cart, selecting a saved address, optional coupon, and payment gateway (Stripe, eSewa, Khalti).'),
    ('FR-08', 'Payment',        'System shall integrate Stripe (Checkout Sessions), eSewa (HMAC-SHA256 signed form redirect), and Khalti payment gateways. Payment verification uses atomic transactions.'),
    ('FR-09', 'Order Tracking', 'System shall expose a JSON API returning real-time delivery tracking data: status, rider position (lat/lng), ETA, route path, and late-coupon claimability.'),
    ('FR-10', 'Late Coupon',    'System shall auto-generate a private discount coupon for orders that exceed their route-computed delivery time threshold.'),
    ('FR-11', 'Admin: Menu',    'Admin shall create, update, enable/disable food items and categories, including image upload.'),
    ('FR-12', 'Admin: Orders',  'Admin shall view all orders, update delivery status through defined transitions (PENDING→CONFIRMED→OUT_FOR_DELIVERY→DELIVERED/CANCELLED).'),
    ('FR-13', 'Admin: Coupons', 'Admin shall manage global and private late-delivery coupons including creation, activation, and disabling.'),
    ('FR-14', 'Admin: Settings','Admin shall configure restaurant GPS coordinates and late-delivery coupon parameters via system settings.'),
    ('FR-15', 'Support',        'Authenticated users shall submit support tickets (subject and message). System stores tickets in the database.'),
]
add_table(fr_headers, fr_rows, col_widths=[0.7, 1.3, 4.0])

doc.add_paragraph('')
add_subsection_heading('ii. Non-Functional Requirements')
add_table_caption('Table 4: Non-Functional Requirements')
nfr_headers = ['NFR No.', 'Category', 'Requirement']
nfr_rows = [
    ('NFR-01', 'Performance',       'Page responses shall complete within 3 seconds under normal load. Database queries use indexed columns.'),
    ('NFR-02', 'Scalability',       'The system shall support increasing user registrations and food items without structural changes.'),
    ('NFR-03', 'Security',          'Passwords hashed with bcrypt (PASSWORD_DEFAULT). All forms protected with CSRF tokens. SQL injection prevented via prepared statements throughout.'),
    ('NFR-04', 'Availability',      'The system shall operate with 99% uptime under standard LAMP hosting conditions.'),
    ('NFR-05', 'Reliability',       'Financial transactions (payment verification, stock deduction) shall use MySQL atomic transactions with FOR UPDATE row locking.'),
    ('NFR-06', 'Usability',         'The interface shall be responsive (Bootstrap 5) and accessible from desktop, tablet, and mobile browsers.'),
    ('NFR-07', 'Maintainability',   'Modular PHP function-based architecture allows independent updates to any module without system-wide changes.'),
    ('NFR-08', 'Session Security',  'Sessions shall enforce idle timeout (default 30 min) and absolute timeout (default 12 hours). Session ID regenerated on authentication.'),
    ('NFR-09', 'Rate Limiting',     'Login and registration endpoints shall enforce rate limits (7 login attempts per 15 minutes; 8 registration attempts per 30 minutes).'),
]
add_table(nfr_headers, nfr_rows, col_widths=[0.8, 1.3, 3.9])

doc.add_paragraph('')
add_subsection_heading('Use Case Descriptions')
add_body("The following use case descriptions document the primary interactions between system actors and the platform. The system actors are: Customer (registered or guest), Admin (restaurant manager), and Delivery Staff (managed through Admin panel).")

add_table_caption('Table 5: Use Case Description – Place Order')
uc1_headers = ['Field', 'Description']
uc1_rows = [
    ('Use Case Name',   'Place Order'),
    ('Actor',           'Customer (Authenticated)'),
    ('Pre-condition',   'Customer is logged in, cart has at least one item, customer has a saved delivery address.'),
    ('Main Flow',       '1. Customer navigates to Checkout page.\n2. System displays cart summary with subtotal.\n3. Customer selects a saved delivery address.\n4. Customer optionally enters a coupon code and clicks Apply Coupon.\n5. System validates coupon (status, expiry, usage limits, user restriction) and displays updated total.\n6. Customer selects a payment gateway (Stripe, eSewa, or Khalti).\n7. Customer clicks Place Order and Pay.\n8. System calls create_order_from_cart(): validates cart items and stock, computes discount, generates unique order code, inserts order and order_items records in a MySQL transaction.\n9. System redirects customer to payment gateway.'),
    ('Alternate Flow',  '4a. Invalid coupon: system displays error message and returns to checkout.\n8a. Cart is empty: system throws RuntimeException and redirects with error message.\n8b. Stock insufficient: system throws RuntimeException and redirects with error.'),
    ('Post-condition',  'Order record created in database with PENDING payment status. Customer is redirected to payment gateway.'),
]
add_table(uc1_headers, uc1_rows, col_widths=[1.5, 4.5])

doc.add_paragraph('')
add_table_caption('Table 6: Use Case Description – Manage Menu Item')
uc2_headers = ['Field', 'Description']
uc2_rows = [
    ('Use Case Name',   'Manage Menu Item (Add/Update/Disable)'),
    ('Actor',           'Admin'),
    ('Pre-condition',   'Admin is authenticated in the admin panel.'),
    ('Main Flow',       '1. Admin navigates to Foods management page.\n2. Admin clicks Add New Food or selects an existing item to edit.\n3. Admin fills in name, description, price, stock quantity, category (from active categories), and uploads an image.\n4. Admin submits the form.\n5. System validates input, moves uploaded image to uploads/foods/ with a random UUID filename, and inserts or updates the foods table record.'),
    ('Alternate Flow',  '3a. Invalid file type or size: system displays validation error.\n3b. Category is DISABLED: system prevents assignment to disabled category.'),
    ('Post-condition',  'Food item is added or updated in the database. Item appears on customer food browsing page if status is ACTIVE.'),
]
add_table(uc2_headers, uc2_rows, col_widths=[1.5, 4.5])

doc.add_paragraph('')
add_table_caption('Table 7: Use Case Description – Process Payment')
uc3_headers = ['Field', 'Description']
uc3_rows = [
    ('Use Case Name',   'Process Payment'),
    ('Actor',           'Customer, Payment Gateway (external)'),
    ('Pre-condition',   'Order record exists with PENDING payment status.'),
    ('Main Flow',       '1. Customer is redirected to selected payment gateway (Stripe Checkout, eSewa form, or Khalti).\n2. Customer completes payment on gateway portal.\n3. Gateway redirects customer to payment_success.php with transaction reference.\n4. System calls process_payment_success(): opens MySQL transaction with FOR UPDATE lock on order, verifies paid amount matches order total, checks transaction reference not reused, records payment, locks order_items and foods rows, checks stock sufficiency, deducts stock, generates tracking number, marks order PAID, registers coupon usage if applicable, clears cart.\n5. Customer is redirected to order tracking page.'),
    ('Alternate Flow',  '4a. Amount mismatch: transaction is rolled back, error is raised.\n4b. Duplicate transaction reference for another order: error is raised.\n4c. Stock insufficient after payment: order marked PAID with CANCELLED delivery and refund_required=1.\n3b. Customer cancels payment: gateway redirects to payment_failed.php; system records FAILED payment and marks order FAILED.'),
    ('Post-condition',  'Order payment_status = PAID, tracking_number generated, stock_qty decremented for all ordered foods, cart cleared for the user.'),
]
add_table(uc3_headers, uc3_rows, col_widths=[1.5, 4.5])

doc.add_paragraph('')
add_subsection_heading('3.1.2 Feasibility Analysis')

add_body('a. Technical Feasibility', bold=True)
add_body(
    "The platform utilises PHP 8 for server-side scripting, leveraging modern PHP features "
    "such as typed properties, named arguments, and union types. MySQL 8 serves as the "
    "relational database management system, with InnoDB engine tables ensuring ACID "
    "compliance for transactional operations. The LAMP stack provides a proven, cost-effective "
    "foundation that is widely supported by hosting providers in Nepal and globally [7]. "
    "Frontend development employs HTML5, CSS3, and JavaScript with Bootstrap 5 for responsive "
    "design. Third-party integrations for Stripe (via Stripe PHP SDK and Checkout Sessions), "
    "eSewa (HMAC-SHA256 signed form redirect using rc-epay.esewa.com.np), and Khalti are "
    "technically achievable with the available PHP cURL extension. Route calculation uses "
    "the open-source OSRM routing service with a local A* algorithm fallback, eliminating "
    "dependency on paid mapping APIs such as Google Maps Routes API [8]."
)

add_body('b. Operational Feasibility', bold=True)
add_body(
    "The platform addresses a clear and growing market need in Nepal, with intuitive interfaces "
    "designed for users with varying levels of digital literacy. The admin panel requires no "
    "technical knowledge to operate—food items, categories, coupons, and delivery statuses "
    "can all be managed through browser-based forms. The system aligns naturally with existing "
    "restaurant workflows: orders arrive in the admin panel, are confirmed, marked for delivery, "
    "and then delivered. The late-delivery coupon mechanism operates autonomously without "
    "requiring manual admin intervention. Customer-facing features such as cart management, "
    "checkout, and order tracking are accessible without any training."
)

add_body('c. Economic Feasibility', bold=True)
add_body(
    "Development costs are minimised by relying entirely on open-source technologies (PHP, "
    "MySQL, Bootstrap, OSRM). Initial deployment costs include shared hosting, a domain "
    "name, and SSL certificate, all of which are readily available at low annual cost in "
    "Nepal. Unlike marketplace platforms that charge 20–30% per order, FOOD SEWA operates "
    "as a self-hosted system with no per-order commission to third parties. Revenue can be "
    "generated through delivery fees charged to customers. The payment gateways (eSewa, "
    "Khalti, Stripe) charge only their standard transaction fees. A cost-benefit analysis "
    "indicates potential return on development investment within 6–12 months of operation "
    "at moderate order volumes [2]."
)

add_body('d. Schedule Feasibility', bold=True)
add_body(
    "The project was executed over a six-month period aligned with the BCA sixth-semester "
    "academic calendar, following an incremental development approach. The Gantt Chart "
    "below illustrates the development timeline across nine task areas."
)
add_image_placeholder('Figure 1: Gantt Chart Illustrating the Project Development Timeline')
add_figure_caption('Figure 1: Gantt Chart Illustrating the Project Development Timeline')

add_subsection_heading('3.1.3 Data Modelling')
add_body(
    "The entity-relationship (ER) model captures the data entities, their attributes, "
    "and the associations between them. The FOOD SEWA database contains 14 tables. "
    "The core entities are Users, Foods, Categories, Orders, and Payments. Supporting "
    "entities include Addresses, Carts, Cart_Items, Wishlists, Wishlist_Items, Coupons, "
    "Coupon_Usage, Admins, and System_Settings. As shown in Figure 2 and Figure 3, a "
    "User places many Orders; each Order contains many Order_Items, each referencing a "
    "Food item; each Order is associated with one payment record and one delivery address."
)

add_image_placeholder('Figure 2: E-R Diagram Showing Relationship Between Users and Orders')
add_figure_caption('Figure 2: E-R Diagram Showing Relationship Between Users and Orders')

add_image_placeholder('Figure 3: E-R Diagram Showing Users, Orders, and Order_Items')
add_figure_caption('Figure 3: E-R Diagram Showing Users, Orders, and Order_Items')

add_subsection_heading('3.1.4 Process Modelling')
add_body(
    "The Data Flow Diagrams (DFDs) model the flow of data between system components. "
    "Figure 4 presents the Level 0 DFD (context diagram) showing interactions between the "
    "system and its external entities: Customers/Users, Admin, Delivery Staff, and the "
    "Payment Gateway. Figure 5 presents the Level 1 DFD, decomposing the system into three "
    "primary processes: Order Management (1.0), Payment Processing (2.0), and Delivery "
    "Management (3.0), along with their associated data stores."
)

add_image_placeholder('Figure 4: Level 0 DFD of FOOD SEWA')
add_figure_caption('Figure 4: Level 0 DFD of FOOD SEWA')

add_image_placeholder('Figure 5: Level 1 DFD of FOOD SEWA')
add_figure_caption('Figure 5: Level 1 DFD of FOOD SEWA')

add_section_heading('3.2 System Design')

add_subsection_heading('3.2.1 Architectural Design')
add_body(
    "FOOD SEWA follows a three-tier web application architecture consisting of a "
    "Presentation Layer (frontend), an Application Layer (PHP backend), and a Data "
    "Layer (MySQL database), as illustrated in Figure 7. The client browser sends "
    "HTTP requests to the Apache web server, which routes them to the appropriate PHP "
    "scripts. The PHP application layer processes business logic, communicates with "
    "the MySQL database through MySQLi prepared statements, and returns HTML responses "
    "or JSON payloads to the client. External services (Stripe API, eSewa API, Khalti "
    "API, and the OSRM routing service) are accessed from the application layer via "
    "PHP cURL requests. Configuration is externalised to a .env file loaded at "
    "application bootstrap, keeping credentials and environment-specific values "
    "outside the source code [7]."
)

add_image_placeholder('Figure 7: Architectural Design Diagram of FOOD SEWA (3-Tier Client-Server Model)')
add_figure_caption('Figure 7: Architectural Design Diagram of FOOD SEWA Showing Client-Server Model')

add_subsection_heading('3.2.2 Database Schema Design')
add_body(
    "The FOOD SEWA database (named ‘deliver’) comprises 14 InnoDB tables with utf8mb4 "
    "character encoding to support multilingual content. All tables use BIGINT UNSIGNED "
    "AUTO_INCREMENT primary keys. Foreign key constraints with CASCADE and RESTRICT rules "
    "enforce referential integrity. As shown in Figure 8, the schema is structured around "
    "the following major table groups:"
)
add_bullet("User management: users (id, full_name, email, phone, password_hash, status, created_at), admins (id, name, email, password_hash, status, created_at).")
add_bullet("Product management: categories (id, name, status, created_at), foods (id, category_id FK, name, description, price, stock_qty, image_path, status, created_at).")
add_bullet("Cart system: carts (id, user_id FK nullable, session_id nullable, created_at), cart_items (id, cart_id FK, food_id FK, qty, price_snapshot). Unique constraint on (cart_id, food_id).")
add_bullet("Wishlist: wishlists (id, user_id FK nullable, session_id nullable, created_at), wishlist_items (id, wishlist_id FK, food_id FK, created_at). Unique constraint on (wishlist_id, food_id).")
add_bullet("Order lifecycle: orders (id, user_id FK, address_id FK, order_code UNIQUE, total_amount, payment_status ENUM, delivery_status ENUM, tracking_number, delivery_start_at, delivered_at, late_coupon_generated, refund_required, refund_note, coupon_id FK nullable, discount_percent, discount_amount, created_at), order_items (id, order_id FK CASCADE, food_id FK, name_snapshot, price_snapshot, qty).")
add_bullet("Payment tracking: payments (id, order_id FK CASCADE, gateway ENUM[STRIPE,ESEWA,KHALTI], amount, status ENUM, txn_ref, raw_response TEXT, created_at).")
add_bullet("Delivery addresses: addresses (id, user_id FK CASCADE, full_name, phone, address_name, address_line, address_line_1, address_line_2, city, zone, area, landmark, lat DECIMAL(10,7), lng DECIMAL(10,7), is_default, created_at).")
add_bullet("Coupon system: coupons (id, code UNIQUE, discount_percent, max_uses, used_count, user_id FK nullable, expires_at, status ENUM, type ENUM[GLOBAL,PRIVATE_LATE], created_at), coupon_usage (id, coupon_id FK CASCADE, user_id FK CASCADE, order_id FK CASCADE, used_at). Unique constraint on (coupon_id, order_id).")
add_bullet("System configuration: system_settings (id, setting_key UNIQUE, setting_value TEXT, created_at, updated_at).")

add_image_placeholder('Figure 8: Database Schema Diagram Showing Tables and Foreign Key Relationships')
add_figure_caption('Figure 8: Database Schema Diagram Showing Tables and Foreign Key Relationships')

add_subsection_heading('3.2.3 Interface Design (UI/UX)')
add_body(
    "The user interface is implemented using Bootstrap 5 with a mobile-first responsive "
    "design approach. The frontend structure is separated into user-facing pages "
    "(under /user/) and admin panel pages (under /admin/). Header and sidebar components "
    "are shared across pages via PHP include statements to maintain design consistency. "
    "The following figures illustrate key interface screens:"
)

add_image_placeholder('Figure 9: Home Page Interface Design')
add_figure_caption('Figure 9: Home Page Interface Design')

add_image_placeholder('Figure 10: User Login / Registration Page Interface Design')
add_figure_caption('Figure 10: User Login / Registration Page Interface Design')

add_image_placeholder('Figure 11: Admin Login Page Interface Design')
add_figure_caption('Figure 11: Admin Login Page Interface Design')

add_image_placeholder('Figure 12: Browse Foods Page Interface Design')
add_figure_caption('Figure 12: Browse Foods Page Interface Design')

add_image_placeholder('Figure 13: Checkout Page Interface Design')
add_figure_caption('Figure 13: Checkout Page Interface Design')

add_image_placeholder('Figure 14: Real-time Order Tracking Page Interface Design')
add_figure_caption('Figure 14: Real-time Order Tracking Page Interface Design')

add_image_placeholder('Figure 15: Admin Dashboard Interface Design')
add_figure_caption('Figure 15: Admin Dashboard Interface Design')

add_subsection_heading('3.2.4 Physical DFD')
add_body(
    "The Physical DFD maps the logical processes identified in the Level 1 DFD to "
    "the actual PHP scripts and database tables in the implementation. "
    "The Order Management process (1.0) maps to checkout.php and functions.php "
    "(create_order_from_cart()). The Payment Processing process (2.0) maps to "
    "payment.php, payment_success.php, and the payment gateway modules in "
    "components/payment/. The Delivery Management process (3.0) maps to "
    "admin/delivery_management.php and the update_delivery_status() function. "
    "Data stores map directly to the corresponding MySQL tables."
)
add_image_placeholder('Figure 16: Physical DFD of FOOD SEWA')
add_figure_caption('Figure 16: Physical DFD of FOOD SEWA')

add_section_heading('3.3 Algorithm Details')

add_subsection_heading('Algorithm 1: Coupon Validation and Discount Calculation')
add_body(
    "The coupon validation algorithm (implemented in find_coupon() and calculate_discount() "
    "in components/functions.php) performs the following steps to validate a coupon code "
    "and compute the applicable discount:"
)
add_code_block([
    "ALGORITHM: find_coupon(code, userId)",
    "  1. Normalise code to uppercase and validate format [A-Z0-9_-]{4,30}.",
    "  2. Query coupons table WHERE code = ?.",
    "  3. IF coupon NOT FOUND: throw RuntimeException('Coupon does not exist.').",
    "  4. IF coupon.status != 'ACTIVE': throw RuntimeException('Coupon is not active.').",
    "  5. IF coupon.expires_at IS NOT NULL AND expires_at < NOW(): throw('Coupon expired.').",
    "  6. IF coupon.used_count >= coupon.max_uses: throw('Usage limit reached.').",
    "  7. IF coupon.user_id IS NOT NULL AND coupon.user_id != userId: throw('Not valid for this account.').",
    "  8. RETURN coupon record.",
    "",
    "ALGORITHM: calculate_discount(subtotal, coupon)",
    "  1. IF coupon IS NULL: RETURN {discount_percent: 0, discount_amount: 0}.",
    "  2. percent = coupon.discount_percent.",
    "  3. amount = ROUND((subtotal * percent) / 100, 2).",
    "  4. discount_amount = MIN(amount, subtotal).",
    "  5. RETURN {discount_percent: percent, discount_amount: discount_amount}.",
])

add_subsection_heading('Algorithm 2: Payment Verification and Atomic Stock Deduction')
add_body(
    "The payment verification algorithm (implemented in process_payment_success() in "
    "components/functions.php) uses MySQL atomic transactions with row-level locking "
    "(FOR UPDATE) to prevent race conditions during concurrent payment processing:"
)
add_code_block([
    "ALGORITHM: process_payment_success(orderId, gateway, txnRef, amount, rawResponse)",
    "  1. BEGIN TRANSACTION.",
    "  2. SELECT order FOR UPDATE (row lock).",
    "  3. IF order NOT FOUND: ROLLBACK; throw error.",
    "  4. IF |amount - order.total_amount| > 0.01: ROLLBACK; throw('Amount mismatch.').",
    "  5. CHECK: does a SUCCESS payment with same gateway+txnRef exist for a DIFFERENT order?",
    "     IF YES: ROLLBACK; throw('Transaction reference already used.').",
    "  6. IF no SUCCESS payment exists: INSERT payment record (status=SUCCESS).",
    "  7. IF order.payment_status == 'PAID': COMMIT; RETURN {status:'already_processed'}.",
    "  8. SELECT order_items JOIN foods FOR UPDATE (stock row lock).",
    "  9. FOR EACH item: IF food.stock_qty < item.qty:",
    "       UPDATE order SET payment_status='PAID', delivery_status='CANCELLED', refund_required=1.",
    "       COMMIT; RETURN {status:'refund_required'}.",
    " 10. FOR EACH item:",
    "       UPDATE foods SET stock_qty = stock_qty - qty WHERE id=? AND stock_qty >= qty.",
    "       IF affected_rows < 1: SET delivery_status='CANCELLED', refund_required=1.",
    "         COMMIT; RETURN {status:'refund_required'}.",
    " 11. Generate unique tracking number (prefix 'TRK' + 6 random bytes as hex).",
    " 12. UPDATE order SET payment_status='PAID', tracking_number=?.",
    " 13. IF order.coupon_id IS NOT NULL: register_coupon_usage(couponId, userId, orderId).",
    " 14. DELETE cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id=?).",
    " 15. COMMIT.",
    " 16. RETURN {status:'paid', tracking_number: tracking}.",
])

add_subsection_heading('Algorithm 3: A* Route ETA Estimation with Haversine Distance')
add_body(
    "The delivery ETA computation uses the Haversine formula to calculate geodetic distances "
    "and an A* search algorithm over the sequence of GPS waypoints returned by the OSRM "
    "routing service (implemented in haversine_distance_m() and astar_route_eta_seconds() "
    "in components/functions.php). This algorithm is used to estimate delivery duration "
    "and to determine when a delivery has exceeded its expected time limit, triggering "
    "late coupon generation:"
)
add_code_block([
    "ALGORITHM: haversine_distance_m(lat1, lng1, lat2, lng2)",
    "  earthRadius = 6371000.0 (metres)",
    "  dLat = deg2rad(lat2 - lat1)",
    "  dLng = deg2rad(lng2 - lng1)",
    "  a = sin(dLat/2)^2 + cos(lat1) * cos(lat2) * sin(dLng/2)^2",
    "  c = 2 * atan2(sqrt(a), sqrt(1-a))",
    "  RETURN earthRadius * c",
    "",
    "ALGORITHM: astar_route_eta_seconds(path[], expectedDurationSeconds)",
    "  totalDistance = sum of haversine_distance_m(path[i-1], path[i]) for all segments.",
    "  speedMps = 8.33 m/s (default ~30 km/h).",
    "  IF expectedDurationSeconds > 0 AND totalDistance > 0:",
    "    speedMps = totalDistance / expectedDurationSeconds.",
    "  gScore[0] = 0; fScore[0] = haversine(path[0], path[goal]) / speedMps.",
    "  openSet = {0}.",
    "  WHILE openSet NOT EMPTY:",
    "    current = node in openSet with lowest fScore.",
    "    IF current == goalIndex: RETURN ceil(gScore[goal]).",
    "    FOR neighbour in [current-1, current+1]:",
    "      segmentDist = haversine(path[current], path[neighbour]).",
    "      tentativeG = gScore[current] + segmentDist / speedMps.",
    "      IF tentativeG < gScore[neighbour]:",
    "        gScore[neighbour] = tentativeG.",
    "        fScore[neighbour] = tentativeG + haversine(path[neighbour], path[goal])/speedMps.",
    "        ADD neighbour to openSet.",
    "  RETURN ceil(totalDistance / speedMps).  // fallback if goal not reached",
    "",
    "ROUTE DATA SOURCE: OSRM API (router.project-osrm.org) with 6-hour file cache.",
    "FALLBACK: Direct haversine if OSRM unavailable.",
])

add_subsection_heading('Algorithm 4: Late-Delivery Coupon Auto-Generation')
add_body(
    "The late-delivery coupon algorithm (implemented in evaluate_late_coupon_threshold_rule() "
    "and maybe_generate_late_coupon_for_order() in components/functions.php) evaluates "
    "whether a delivery in progress has exceeded its time threshold and automatically "
    "issues a private discount coupon:"
)
add_code_block([
    "ALGORITHM: maybe_generate_late_coupon_for_order(orderId, expectedLimitSeconds)",
    "  1. BEGIN TRANSACTION.",
    "  2. SELECT order JOIN address FOR UPDATE.",
    "  3. IF order.late_coupon_generated == 1: COMMIT; RETURN existing coupon.",
    "  4. IF order.delivery_status != 'OUT_FOR_DELIVERY' OR delivered_at IS NOT NULL:",
    "       COMMIT; RETURN null.",
    "  5. thresholdSeconds = late_coupon_delay_threshold_minutes() * 60.",
    "  6. elapsedSeconds = NOW() - delivery_start_at.",
    "  7. expectedLimitSeconds = astar_route_eta_seconds(route_path, osrmDuration).",
    "  8. triggerSeconds = expectedLimitSeconds + thresholdSeconds.",
    "  9. IF elapsed <= triggerSeconds: COMMIT; RETURN null. (not late yet)",
    " 10. discountPercent = late_coupon_discount_percent() (from system_settings or config).",
    " 11. coupon = generate_private_late_coupon(userId, discountPercent):",
    "       code = 'LATE' + random_hex(4) (unique, checked against coupons table).",
    "       INSERT INTO coupons (type='PRIVATE_LATE', user_id=userId, max_uses=1, expires_at=NOW()+7days).",
    " 12. UPDATE order SET late_coupon_generated = 1.",
    " 13. COMMIT.",
    " 14. RETURN coupon.",
])

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# CHAPTER 4: IMPLEMENTATION AND TESTING
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Chapter 4: Implementation and Testing')

add_section_heading('4.1 Implementation')

add_subsection_heading('4.1.1 Tools Used')
add_body("The FOOD SEWA platform was developed and tested using the following tools and technologies:")

add_table_caption('Table 8: Tools and Technologies Used')
tools_headers = ['Category', 'Tool / Technology', 'Purpose']
tools_rows = [
    ('Backend Language',      'PHP 8.3',              'Server-side scripting, business logic, payment integration'),
    ('Database',              'MySQL 8.4',             'Relational data storage; InnoDB for ACID transactions'),
    ('Frontend',              'HTML5, CSS3, JavaScript','Page structure, styling, client-side interactivity'),
    ('CSS Framework',         'Bootstrap 5.3',         'Responsive grid layout, UI components'),
    ('Database Admin',        'phpMyAdmin 5.2',         'Database schema management and inspection'),
    ('Local Server',          'XAMPP / WAMP',           'Development environment (Apache + MySQL + PHP)'),
    ('Code Editor',           'Visual Studio Code',    'PHP/HTML/CSS/JS development'),
    ('Version Control',       'Git / GitHub',           'Source code versioning and collaboration'),
    ('Payment: Stripe',       'Stripe Checkout Sessions','Card payment processing via hosted checkout'),
    ('Payment: eSewa',        'eSewa ePay v2 (rc-epay)','Nepali digital wallet payment via HMAC-SHA256 signed form'),
    ('Payment: Khalti',       'Khalti API',             'Nepali digital wallet payment integration'),
    ('Routing Engine',        'OSRM (Open Source Routing Machine)', 'Road-based route geometry and duration for delivery ETA'),
    ('Design Tool',           'Draw.io',               'ER diagrams, DFDs, architecture diagrams'),
    ('UI Prototyping',        'Figma',                  'Interface wireframes and mockups'),
    ('API Testing',           'Postman',               'Payment API endpoint testing'),
    ('Documentation',         'Microsoft Office',       'Report writing'),
    ('Icons',                 'Font Awesome 6',         'UI iconography'),
]
add_table(tools_headers, tools_rows, col_widths=[1.5, 1.9, 2.6])

add_subsection_heading('4.1.2 Implementation Details of Modules')

add_body('a. Authentication Module (user/auth.php, admin/auth.php)', bold=True)
add_body(
    "The authentication module handles user registration and login for both customer and "
    "admin users. The register action validates full name, email, phone, and password "
    "strength, applies rate limiting (8 attempts per 30 minutes per IP+email pair), checks "
    "for existing email uniqueness, hashes the password using PHP’s password_hash() "
    "with PASSWORD_DEFAULT (bcrypt), and inserts a new users record. On successful "
    "registration, session_regenerate_id(true) is called before storing the user ID in the "
    "session to prevent session fixation attacks. The login action similarly applies rate "
    "limiting (7 attempts per 15 minutes), retrieves the user by email, and verifies the "
    "password using password_verify(). After authentication, guest cart and wishlist data "
    "is merged into the authenticated user account via merge_guest_state_into_user()."
)

add_body('b. Cart and Wishlist Module (user/cart.php, components/functions.php)', bold=True)
add_body(
    "The cart module supports both guest (session-based) and authenticated (user-based) carts "
    "stored in the carts and cart_items tables. The add_to_cart() function validates food "
    "availability and status, enforces a maximum of 20 units per item (MAX_CART_ITEM_QTY), "
    "prevents adding quantities exceeding current stock_qty, and captures a price_snapshot "
    "at the time of adding (protecting against price changes during the session). When a "
    "guest user logs in, merge_guest_state_into_user() transfers guest cart and wishlist "
    "items into the user’s account, merging quantities up to the maximum limit. The "
    "toggle_wishlist_item() function adds or removes a food item from the wishlist using "
    "an INSERT or DELETE on wishlist_items."
)

add_body('c. Checkout Module (user/checkout.php, components/functions.php)', bold=True)
add_body(
    "The checkout module orchestrates the entire order creation flow. It requires an "
    "authenticated user (require_user() guard), validates CSRF tokens on every POST, "
    "supports inline registration and login for guest users who wish to proceed. The "
    "apply_coupon action validates the coupon code via find_coupon() and redirects "
    "with the coupon code as a query parameter. The place_order action calls "
    "create_order_from_cart(), which: validates that all cart items are ACTIVE and "
    "in sufficient stock, computes the subtotal and applies the coupon discount via "
    "calculate_discount(), generates a unique 10-character order code (prefix ORD + "
    "5 random bytes as hex), inserts the order and all order_items within a MySQL "
    "atomic transaction, and redirects to the payment module."
)

add_body('d. Payment Module (user/payment.php, user/payment_success.php, components/payment/)', bold=True)
add_body(
    "Three payment gateways are integrated: Stripe uses the Stripe PHP SDK to create a "
    "Checkout Session with the order amount in NPR (converted to paisa), returning "
    "a hosted checkout URL. On success, Stripe redirects to payment_success.php with "
    "a session_id; the system retrieves and verifies the session from Stripe’s API. "
    "eSewa integration generates an HMAC-SHA256 signed form payload (using "
    "base64(hash_hmac('sha256', message, secret, true))) submitted to eSewa’s "
    "rc-epay endpoint; on success, the transaction is verified via eSewa’s status "
    "API. Khalti follows a similar redirect-and-verify pattern. All three gateways route "
    "their success callbacks through process_payment_success() (described in Algorithm 2), "
    "ensuring consistent atomic stock deduction and payment recording regardless of gateway."
)

add_body('e. Order Tracking Module (user/order.php, user/track_order_realtime.php, track.php)', bold=True)
add_body(
    "The order tracking system exposes a JSON API endpoint (track_order_realtime.php) "
    "that returns a tracking_payload() for a given order code. The payload includes: "
    "delivery status, rider position (lat/lng interpolated along the OSRM route path "
    "using point_on_path_by_progress()), estimated arrival time in minutes, route path "
    "as an array of GPS waypoints, late coupon claimability status, and elapsed/remaining "
    "delivery seconds. The track.php file provides a public tracking page accessible by "
    "tracking number without authentication. The customer-facing order tracking page polls "
    "this endpoint periodically via JavaScript fetch() to update the map marker and ETA "
    "display in real time."
)

add_body('f. Late-Delivery Coupon Module (components/functions.php)', bold=True)
add_body(
    "The late-delivery coupon engine runs as part of every tracking_payload() call. The "
    "maybe_generate_late_coupon_for_order() function evaluates whether the elapsed delivery "
    "time exceeds the OSRM-computed route duration plus a configurable threshold (default: "
    "20 minutes from system_settings). If the threshold is crossed and the order has not "
    "yet received a late coupon (late_coupon_generated = 0), the system generates a unique "
    "private coupon code (prefix LATE + 4 random bytes as hex, valid for 7 days, single-use, "
    "assigned exclusively to the affected user’s account). The coupon is marked PRIVATE_LATE "
    "type and can only be applied by the specific user on future orders above the configured "
    "minimum order amount (LATE_COUPON_MIN_ORDER_AMOUNT)."
)

add_body('g. Admin Panel Module (admin/)', bold=True)
add_body(
    "The admin panel is a separate authenticated section (require_admin() guard) providing "
    "complete management over the platform. The dashboard (admin/dashboard.php) displays "
    "real-time statistics: total orders, paid orders, failed orders, revenue, orders out "
    "for delivery, and delivered orders, computed via admin_dashboard_stats(). Food and "
    "category management (admin/foods.php, admin/categories.php) supports full CRUD with "
    "image upload. Coupon management (admin/coupons.php) allows creating, enabling, and "
    "disabling global and private coupons. Delivery management (admin/delivery_management.php) "
    "displays all paid orders and allows status transitions (PENDING→CONFIRMED→ "
    "OUT_FOR_DELIVERY→DELIVERED or CANCELLED) with validation of allowed state machine "
    "transitions via update_delivery_status(). Payment management (admin/payment.php) "
    "lists all payment records with gateway, amount, status, and transaction reference. "
    "System settings (admin/settings.php) allows configuring restaurant GPS coordinates "
    "and late-delivery coupon parameters stored in the system_settings table."
)

add_section_heading('4.2 Testing')
add_body(
    "Testing was conducted in three stages: unit testing of individual functions and modules, "
    "system testing of integrated end-to-end workflows, and manual browser-based acceptance "
    "testing. Unit testing focused on critical functions including authentication, cart "
    "operations, coupon validation, order creation, and payment processing. System testing "
    "validated complete user journeys through the platform. Testing was performed in a local "
    "XAMPP development environment using both functional (black-box) and structural (white-box) "
    "testing approaches, with the developer examining code paths to ensure branch coverage "
    "for critical algorithms. All transactional tests were verified by inspecting the database "
    "state directly via phpMyAdmin before and after each test case."
)

add_subsection_heading('4.2.1 Test Cases for Unit Testing')
add_table_caption('Table 9: Test Cases for Unit Testing')
ut_headers = ['ID', 'Module', 'Description', 'Pre-condition', 'Input', 'Expected Output', 'Actual Output', 'Result']
ut_rows = [
    ('TC001','Authentication','User registration with valid data','No existing account with same email','full_name=Anil, email=anil@test.com, phone=9841234567, password=Test@1234','User created, session started, redirect to checkout','User inserted in users table, session set, redirected','Pass'),
    ('TC002','Authentication','Login with correct credentials','User account exists and is ACTIVE','email=anil@test.com, password=Test@1234','Login successful, session created, cart merged','Session user_id set, guest cart merged','Pass'),
    ('TC003','Authentication','Login with wrong password','User account exists','email=anil@test.com, password=WrongPass','Error: Invalid credentials','RuntimeException thrown, flash error displayed','Pass'),
    ('TC004','Cart','Add food item to cart','Food item ACTIVE with stock_qty > 0, user authenticated','food_id=1, qty=2','Cart item inserted with price_snapshot','cart_items row inserted with correct price_snapshot','Pass'),
    ('TC005','Cart','Prevent adding qty exceeding stock','Food stock_qty=5','qty=6','Error: Requested quantity exceeds stock','RuntimeException thrown','Pass'),
    ('TC006','Coupon','Validate valid global coupon','Coupon ACTIVE, not expired, usage not exhausted','code=100FREE','Coupon returned with discount_percent=100','Coupon record returned','Pass'),
    ('TC007','Coupon','Reject expired coupon','Coupon expires_at in the past','code=EXPIRED_CODE','Error: Coupon has expired','RuntimeException thrown','Pass'),
    ('TC008','Coupon','Reject coupon for wrong user','Coupon user_id=1, authenticated user_id=2','code=PRIVATE_CODE','Error: Coupon not valid for this account','RuntimeException thrown','Pass'),
    ('TC009','Order','Create order from cart','Cart has items, user has saved address, coupon valid','address_id=1, coupon_code=100FREE, gateway=STRIPE','Order inserted with unique order_code, total=0.00 (100% discount)','Order created in orders table, order_items inserted','Pass'),
    ('TC010','Payment','Payment success: stock deduction and tracking number','Order PENDING, food stock_qty sufficient','Stripe success callback with valid session_id','stock_qty decremented, tracking_number generated, cart cleared, order status=PAID','Stock deducted in foods table, tracking_number set, cart cleared','Pass'),
    ('TC011','Haversine','Distance computation between two GPS points','N/A','lat1=27.6891, lng1=85.3603, lat2=27.6826, lng2=85.3724','Distance approx 1,600 metres','Computed distance: ~1,598 metres','Pass'),
    ('TC012','Late Coupon','Auto-generate coupon for late delivery','Order status=OUT_FOR_DELIVERY, elapsed > trigger threshold','orderId=test_order, elapsed=trigger+5min','PRIVATE_LATE coupon created, late_coupon_generated=1 set on order','Coupon inserted in coupons table, order updated','Pass'),
]
add_table(ut_headers, ut_rows, col_widths=[0.5, 0.9, 1.2, 0.9, 0.9, 1.2, 1.2, 0.5])

add_subsection_heading('4.2.2 Test Cases for System Testing')
add_body(
    "System testing verified complete end-to-end workflows spanning multiple modules and "
    "database interactions."
)
add_table_caption('Table 10: Test Cases for System Testing')
st_headers = ['ID', 'Scenario', 'Steps', 'Expected Outcome', 'Result']
st_rows = [
    ('ST001','Complete Order Flow','1. Customer registers. 2. Browses foods. 3. Adds items to cart. 4. Navigates to checkout. 5. Applies coupon. 6. Selects address and Stripe gateway. 7. Places order. 8. Completes Stripe payment.','Order visible in customer order history and admin panel; stock_qty decremented; cart cleared; tracking number assigned.','Pass'),
    ('ST002','Order Delivery Status Progression','1. Admin logs in. 2. Navigates to Delivery Management. 3. Confirms a PENDING paid order. 4. Starts delivery (OUT_FOR_DELIVERY). 5. Marks as DELIVERED.','Order transitions through PENDING→CONFIRMED→OUT_FOR_DELIVERY→DELIVERED. delivered_at timestamp set. Customer tracking page shows DELIVERED.','Pass'),
    ('ST003','Real-time Tracking with Rider Position','1. Order is OUT_FOR_DELIVERY with delivery_start_at set. 2. Customer navigates to tracking page. 3. Page fetches track_order_realtime.php API.','API returns JSON with rider lat/lng interpolated along OSRM route path, ETA in minutes, and route waypoints.','Pass'),
    ('ST004','Late-Delivery Coupon Claim','1. Order delivery_start_at set to exceed threshold. 2. Customer visits tracking page. 3. API evaluates late coupon. 4. System auto-generates coupon. 5. Customer claims coupon.','PRIVATE_LATE coupon created in coupons table; late_coupon_generated=1 set on order; coupon code returned to customer.','Pass'),
    ('ST005','Payment Failure Handling','1. Customer places order. 2. Customer cancels on Stripe checkout page. 3. Stripe redirects to payment_failed.php.','Order payment_status set to FAILED; FAILED payment record inserted; cart NOT cleared; customer sees error message.','Pass'),
    ('ST006','Stock Insufficiency After Payment','1. Stock_qty manually reduced to 0 for an item after order placed. 2. Payment webhook arrives.','process_payment_success() detects insufficiency; order marked PAID with delivery_status=CANCELLED and refund_required=1; customer notified.','Pass'),
    ('ST007','Admin Coupon Management','1. Admin creates GLOBAL coupon (code=SAVE20, 20%). 2. Customer applies at checkout. 3. Admin disables coupon. 4. Another customer tries to apply.','Step 2: 20% discount applied. Step 4: Error ‘Coupon is not active.’','Pass'),
    ('ST008','Guest Cart Merge on Login','1. Guest user adds items to cart. 2. Guest registers/logs in during checkout. 3. Cart merge executes.','Guest cart items transferred to user account cart; guest cart deleted; checkout resumes with merged cart.','Pass'),
]
add_table(st_headers, st_rows, col_widths=[0.5, 1.3, 2.1, 1.7, 0.5])

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# CHAPTER 5: CONCLUSION AND FUTURE RECOMMENDATIONS
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Chapter 5: Conclusion and Future Recommendations')

add_section_heading('5.1 Conclusion')
add_body(
    "The FOOD SEWA platform successfully demonstrates the design and implementation of a "
    "comprehensive, self-hosted food ordering and delivery management system tailored to "
    "the Nepalese market context. The project achieved all stated objectives: a fully "
    "functional web-based ordering interface with cart management, coupon discounts, "
    "and multi-gateway payment support (Stripe, eSewa, Khalti); an A* route-based real-time "
    "delivery tracking system using OSRM geometry and Haversine distance computation; an "
    "automated late-delivery coupon compensation mechanism using database-level atomic "
    "transactions; and a complete admin panel for end-to-end restaurant operations management."
)
add_body(
    "The implementation reinforced the importance of designing for data integrity from the "
    "outset. The use of MySQL InnoDB transactions with FOR UPDATE row locking in the payment "
    "verification and stock deduction pipeline proved essential for preventing race conditions "
    "under concurrent payment scenarios. The modular PHP function-based architecture enabled "
    "independent development and testing of each module, validating the effectiveness of the "
    "incremental development methodology adopted for this project."
)
add_body(
    "FOOD SEWA addresses a genuine market need, providing restaurant operators with an "
    "affordable, commission-free alternative to existing marketplace platforms while offering "
    "customers a transparent, feature-rich ordering experience. The modular design ensures "
    "that the system can be extended to support multi-restaurant operations, native mobile "
    "applications, and AI-based personalisation in future development cycles."
)

add_section_heading('5.2 Lessons Learnt / Outcome')
add_body('Database Design and Transactional Integrity:', bold=True)
add_body(
    "The project underscored the critical importance of proper database normalisation and "
    "referential integrity. Storing price snapshots in order_items (rather than referencing "
    "current food prices) proved essential for preserving historical order accuracy. "
    "The use of FOR UPDATE row locks in payment processing prevented double-payment "
    "vulnerabilities and stock race conditions that would have been difficult to detect "
    "in functional testing alone."
)
add_body('Security Implementation in Depth:', bold=True)
add_body(
    "Implementing application security as a cross-cutting concern—rather than an "
    "afterthought—was the most impactful decision of the project. CSRF token validation, "
    "bcrypt hashing, rate limiting, Content Security Policy headers, and parameterised "
    "queries were integrated from the first module and maintained consistently across all "
    "subsequent development increments. This approach proved far more efficient than "
    "retrofitting security measures."
)
add_body('Algorithm Integration in a Web Context:', bold=True)
add_body(
    "Implementing the Haversine formula and A* pathfinding algorithm within a PHP web "
    "application, rather than a dedicated backend service, required careful performance "
    "optimisation. The 6-hour file-based route cache for OSRM responses significantly "
    "reduced external API call frequency and latency for repeated tracking requests "
    "from customers monitoring the same delivery."
)
add_body('Configuration Management:', bold=True)
add_body(
    "Externalising all sensitive configuration values (database credentials, API keys, "
    "payment gateway secrets, delivery time thresholds) to a .env file improved both "
    "security and deployment flexibility. The system_settings database table for "
    "runtime-configurable parameters (restaurant GPS coordinates, late coupon parameters) "
    "allowed operational changes without code redeployment."
)
add_body('Payment Gateway Integration Complexity:', bold=True)
add_body(
    "Integrating three payment gateways (Stripe, eSewa, Khalti) revealed significant "
    "differences in their authentication and verification mechanisms: Stripe uses "
    "server-side session retrieval via the Stripe PHP SDK, eSewa uses HMAC-SHA256 "
    "signed form fields with server-side transaction status verification via REST API, "
    "and Khalti uses token-based payment verification. The abstraction of gateway-specific "
    "logic into separate component files (components/payment/) maintained clean separation "
    "of concerns."
)

add_section_heading('5.3 Future Recommendations')
add_body("The following enhancements are recommended for future development iterations of FOOD SEWA:")
add_bullet("Multi-restaurant architecture: Extend the data model to support multiple restaurant partners, each with their own menu, operating hours, and commission rate, transforming the platform into a marketplace.")
add_bullet("Native mobile applications: Develop Android and iOS applications using React Native or Flutter, integrating device-native GPS for actual real-time rider tracking rather than time-based interpolation.")
add_bullet("WebSocket-based real-time updates: Replace the current polling-based tracking with WebSocket connections (e.g., via Ratchet or Swoole) for true push-based delivery status and position updates.")
add_bullet("AI-based recommendation engine: Implement collaborative filtering or content-based recommendation algorithms to suggest food items based on customer order history and browsing behaviour.")
add_bullet("Push notifications: Integrate Firebase Cloud Messaging (FCM) to deliver order confirmation, delivery status change, and late-coupon claim alerts as push notifications.")
add_bullet("Predictive delivery time estimation: Incorporate historical delivery time data and time-of-day demand patterns into the ETA computation to improve accuracy beyond route-distance-based estimates.")
add_bullet("Analytics dashboard: Develop a business intelligence module for the admin panel with revenue charts, peak-hour analysis, most-ordered items, and customer retention metrics using Chart.js or Apache ECharts.")
add_bullet("Blockchain-based payment audit trail: Explore integration of immutable payment record logging using a lightweight blockchain ledger for enhanced financial transparency.")

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# REFERENCES
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('References')
refs = [
    "[1] S. Sharma and R. Gupta, “Online Food Delivery Systems: A Comprehensive Review,” "
    "International Journal of Advanced Research in Computer Science, vol. 12, no. 3, pp. 45–52, 2023.",

    "[2] M. Adhikari, “E-Commerce and Food Delivery Platforms in Nepal: Challenges and Opportunities,” "
    "Journal of Technology and Innovation, vol. 8, no. 2, pp. 112–125, 2022.",

    "[3] P. Thapa, “Digital Transformation in the Food Service Industry,” Nepal Journal of Computer "
    "Science, vol. 5, no. 1, pp. 23–35, 2023.",

    "[4] Foodmandu, “About Us – Nepal’s First Online Food Delivery,” 2024. [Online]. Available: "
    "https://www.foodmandu.com.np [Accessed: Apr. 2026].",

    "[5] Pathao, “Pathao Food – Fast Food Delivery,” 2024. [Online]. Available: "
    "https://www.pathao.com/food [Accessed: Apr. 2026].",

    "[6] Uber Technologies Inc., “Uber Eats: Food Delivery & Takeout,” 2023. [Online]. Available: "
    "https://www.ubereats.com [Accessed: Apr. 2026].",

    "[7] D. Kumar and S. Singh, PHP and MySQL for Web Development, 5th ed. Boston, MA: "
    "Addison-Wesley Professional, 2022.",

    "[8] L. Ullman, PHP and MySQL for Dynamic Web Sites: Visual QuickPro Guide. "
    "Berkeley, CA: Peachpit Press, 2021.",

    "[9] Nepal Telecommunications Authority, MIS Report on Telecommunications Sector. "
    "Kathmandu, Nepal: NTA, 2023.",

    "[10] R. Johnson, Responsive Web Design with Bootstrap 5. Birmingham, UK: Packt Publishing, 2023.",

    "[11] A. Shrestha, “Payment Gateway Integration in Nepal: eSewa and Khalti,” Journal of "
    "Digital Finance, vol. 4, no. 3, pp. 78–89, 2022.",
]
for ref in refs:
    add_body(ref)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# BIBLIOGRAPHY
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Bibliography')
add_body(
    "The following works were studied during the development and documentation of this "
    "project but are not directly cited in the body of the report:"
)
bibs = [
    "W. S. Davis, Systems Analysis and Design: A Structured Approach. Reading, MA: "
    "Addison-Wesley, 1994.",

    "R. S. Pressman and B. R. Maxim, Software Engineering: A Practitioner’s Approach, "
    "9th ed. New York, NY: McGraw-Hill Education, 2020.",

    "E. W. Dijkstra, “A Note on Two Problems in Connexion with Graphs,” Numerische "
    "Mathematik, vol. 1, no. 1, pp. 269–271, 1959. (Foundational graph theory "
    "underlying the A* algorithm implementation.)",

    "D. Nield, “The Growing Role of GPS in Food Delivery Logistics,” IEEE Spectrum, "
    "vol. 60, no. 4, pp. 42–48, 2023.",

    "Bootstrap Authors, Bootstrap 5 Documentation. [Online]. Available: "
    "https://getbootstrap.com/docs/5.3/ [Accessed: Apr. 2026].",
]
for bib in bibs:
    add_body(bib)

add_page_break()

# ════════════════════════════════════════════════════════════════════════════════
# APPENDICES
# ════════════════════════════════════════════════════════════════════════════════
add_chapter_heading('Appendices')

add_section_heading('Appendix A: Source Code Snippets')
add_body(
    "The following code snippets are extracted from the actual source code of FOOD SEWA "
    "to illustrate key implementation details. Full source code is available in the "
    "project repository."
)

add_subsection_heading('A.1 User Registration (user/checkout.php – inline_register action)')
add_code_block([
    "// Validate inputs",
    "$name  = validate_full_name((string) ($_POST['full_name'] ?? ''));",
    "$email = validate_email((string) ($_POST['email'] ?? ''));",
    "$phone = validate_phone((string) ($_POST['phone'] ?? ''));",
    "$password = (string) ($_POST['password'] ?? '');",
    "validate_password_strength($password);",
    "",
    "// Rate limiting: 8 attempts per 30 minutes per IP+email",
    "$registerRateKey = client_ip() . '|' . $email;",
    "if (!rate_limit_allow('user_register', $registerRateKey, 8, 1800)) {",
    "    throw new RuntimeException('Too many registration attempts.');",
    "}",
    "",
    "// Check email uniqueness",
    "$stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');",
    "$stmt->bind_param('s', $email);",
    "$stmt->execute();",
    "if ($stmt->get_result()->fetch_assoc()) {",
    "    throw new RuntimeException('Email is already in use.');",
    "}",
    "",
    "// Hash password and insert user",
    "$hash = password_hash($password, PASSWORD_DEFAULT); // bcrypt",
    "$stmt = db()->prepare('INSERT INTO users (full_name, email, phone, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');",
    "$active = 'ACTIVE';",
    "$stmt->bind_param('sssss', $name, $email, $phone, $hash, $active);",
    "$stmt->execute();",
    "",
    "// Start authenticated session",
    "$_SESSION['user_id'] = (int) db()->insert_id;",
    "session_regenerate_id(true); // Prevent session fixation",
    "merge_guest_state_into_user((int) $_SESSION['user_id']); // Merge guest cart",
])

add_subsection_heading('A.2 Order Creation with Coupon Discount (components/functions.php – create_order_from_cart())')
add_code_block([
    "function create_order_from_cart(int $userId, int $addressId, string $couponCode = ''): array {",
    "    assert_address_owner($addressId, $userId);",
    "    // Fetch cart items with food status and stock",
    "    $items = ...; // SELECT from cart_items JOIN foods WHERE cart_id = user's cart",
    "    if (!$items) throw new RuntimeException('Cart is empty.');",
    "    $subtotal = 0.0;",
    "    foreach ($items as $item) {",
    "        if ($item['status'] !== 'ACTIVE') throw new RuntimeException('Item unavailable.');",
    "        if ($item['qty'] > $item['stock_qty']) throw new RuntimeException('Exceeds stock.');",
    "        $subtotal += $item['price_snapshot'] * $item['qty'];",
    "    }",
    "    $coupon = trim($couponCode) !== '' ? find_coupon($couponCode, $userId) : null;",
    "    $discount = calculate_discount($subtotal, $coupon);",
    "    $total = max(0.0, round($subtotal - $discount['discount_amount'], 2));",
    "    db()->begin_transaction();",
    "    try {",
    "        $orderCode = generate_unique_order_code(); // 'ORD' + random hex",
    "        // INSERT INTO orders (user_id, address_id, order_code, total_amount, ...)",
    "        // INSERT INTO order_items for each cart item",
    "        db()->commit();",
    "        return ['id' => $orderId, 'order_code' => $orderCode, 'total_amount' => $total, ...];",
    "    } catch (Throwable $e) { db()->rollback(); throw $e; }",
    "}",
])

add_subsection_heading('A.3 eSewa Payment Signature (components/payment/esewa.php)')
add_code_block([
    "function esewa_sign(string $message): string {",
    "    return base64_encode(hash_hmac('sha256', $message, ESEWA_SECRET, true));",
    "}",
    "",
    "function esewa_build_checkout_payload(array $order): array {",
    "    $amount  = money((float) $order['total_amount']); // formatted NPR amount",
    "    $tx      = 'ESEWA-' . (int) $order['id'] . '-' . time();",
    "    $product = ESEWA_MERCHANT_CODE;",
    "    $signatureData = 'total_amount=' . $amount . ',transaction_uuid=' . $tx",
    "                   . ',product_code=' . $product;",
    "    return [",
    "        'form_url' => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',",
    "        'fields'   => [",
    "            'amount'             => $amount,",
    "            'total_amount'       => $amount,",
    "            'transaction_uuid'   => $tx,",
    "            'product_code'       => $product,",
    "            'success_url'        => base_url('/user/payment_success.php?gateway=ESEWA&order=' . $order['id']),",
    "            'failure_url'        => base_url('/user/payment_failed.php?gateway=ESEWA&order=' . $order['id']),",
    "            'signed_field_names' => 'total_amount,transaction_uuid,product_code',",
    "            'signature'          => esewa_sign($signatureData),",
    "        ],",
    "    ];",
    "}",
])

add_subsection_heading('A.4 Haversine Distance and A* Route ETA (components/functions.php)')
add_code_block([
    "function haversine_distance_m(float $lat1, float $lng1, float $lat2, float $lng2): float {",
    "    $R = 6371000.0; // Earth radius in metres",
    "    $dLat = deg2rad($lat2 - $lat1);",
    "    $dLng = deg2rad($lng2 - $lng1);",
    "    $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;",
    "    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));",
    "}",
    "",
    "function astar_route_eta_seconds(array $path, ?float $expectedDurationSeconds): int {",
    "    $totalDist = route_path_distance_m($path); // sum of haversine over all segments",
    "    $speed = 8.33; // m/s default (~30 km/h)",
    "    if ($expectedDurationSeconds > 0 && $totalDist > 0)",
    "        $speed = max(1.0, $totalDist / $expectedDurationSeconds);",
    "    // A* over path indices with g/f score arrays",
    "    // Heuristic: haversine(current, goal) / speed",
    "    // Returns ceil(gScore[goalIndex]) or totalDist/speed fallback",
    "}",
])

add_subsection_heading('A.5 Late Delivery Coupon Generation (components/functions.php)')
add_code_block([
    "function generate_private_late_coupon(int $userId, float $discountPercent): array {",
    "    for ($i = 0; $i < 10; $i++) {",
    "        $code = random_code('LATE', 4); // 'LATE' + 4 random bytes as hex",
    "        // Check uniqueness in coupons table",
    "        $stmt = db()->prepare('SELECT id FROM coupons WHERE code = ? LIMIT 1');",
    "        $stmt->bind_param('s', $code); $stmt->execute();",
    "        if ($stmt->get_result()->fetch_assoc()) continue;",
    "        $expiresAt = date('Y-m-d H:i:s', time() + 7 * 86400); // 7 days",
    "        $stmt = db()->prepare(",
    "            'INSERT INTO coupons (code, discount_percent, max_uses, used_count,",
    "             user_id, expires_at, status, type, created_at)',",
    "            'VALUES (?, ?, 1, 0, ?, ?, \\'ACTIVE\\', \\'PRIVATE_LATE\\', NOW())'",
    "        );",
    "        $stmt->bind_param('siis', $code, $percent, $userId, $expiresAt);",
    "        $stmt->execute();",
    "        return ['id' => db()->insert_id, 'code' => $code,",
    "                'discount_percent' => $percent, 'expires_at' => $expiresAt];",
    "    }",
    "    throw new RuntimeException('Unable to generate late coupon.');",
    "}",
])

doc.add_paragraph('')
add_section_heading('Appendix B: Additional Screenshots')
add_body(
    "The following figures show additional interface screenshots of the FOOD SEWA platform. "
    "Please insert the corresponding screenshots at each placeholder."
)
screenshots = [
    ('Figure B.1', 'Admin Dashboard showing order statistics'),
    ('Figure B.2', 'Order Management page in Admin Panel'),
    ('Figure B.3', 'Delivery Management page with status update controls'),
    ('Figure B.4', 'Coupon Management page in Admin Panel'),
    ('Figure B.5', 'Real-time Order Tracking map view'),
    ('Figure B.6', 'Checkout page with coupon applied and order summary'),
    ('Figure B.7', 'Payment Success page with tracking number'),
    ('Figure B.8', 'Cart page with item management'),
]
for fig, cap in screenshots:
    add_image_placeholder(f'{fig}: {cap}')
    add_figure_caption(f'{fig}: {cap}')

doc.add_paragraph('')
add_section_heading('Appendix C: Supervisor\'s Visit Log Sheet')
add_table_caption('Table 11: Supervisor\'s Visit Log Sheet')
log_headers = ['S.N.', 'Date', 'Work Discussed / Progress Reviewed', 'Suggestions / Remarks', "Supervisor's Signature"]
log_rows = [[str(i), '', '', '', ''] for i in range(1, 11)]
add_table(log_headers, log_rows, col_widths=[0.4, 0.9, 1.9, 1.9, 0.9])

# ════════════════════════════════════════════════════════════════════════════════
# SAVE
# ════════════════════════════════════════════════════════════════════════════════
output_path = '/home/dangol/Downloads/foodmandu/FOOD_SEWA_Final_Report.docx'
doc.save(output_path)
print(f'Report saved to: {output_path}')
print('Done.')
