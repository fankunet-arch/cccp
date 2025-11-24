import datetime
from playwright.sync_api import sync_playwright
import os

# 1. Logic Simulation (Ported from dts_main.php)
def simulate_logic():
    today = datetime.date.today()
    future_days = 180 # Filter
    future_dt = today + datetime.timedelta(days=future_days)

    # Scenario:
    # Due Date = Today + 100 days
    # Window = 180 days before Due Date.
    # Window Start = (Today + 100) - 180 = Today - 80.

    deadline_date = today + datetime.timedelta(days=100)
    window_start_date = deadline_date - datetime.timedelta(days=180)

    # Object Data
    obj = {
        'id': 99,
        'subject_name': 'Test Corp',
        'object_name': 'Window Test Object',
        'object_type_main': 'Test',
        'object_type_sub': 'Sim',
        'next_deadline_date': deadline_date,
        'next_window_start_date': window_start_date,
        'next_cycle_date': None,
        'next_follow_up_date': None,
        'locked_until_date': None
    }

    nodes = []

    # Logic from dts_main.php (Patched)
    node_data = None

    # Priority 1: Window Start
    if obj['next_window_start_date']:
        ws_dt = obj['next_window_start_date']
        dl_dt = obj['next_deadline_date']

        if ws_dt > today:
            # Future Window
            if ws_dt <= future_dt:
                days_wait = (ws_dt - today).days
                node_data = {
                    'date': ws_dt,
                    'type': 'window_start',
                    'type_name': '即将开始',
                    'urgency': 'info',
                    'urgency_text': f"还有 {days_wait} 天开始",
                    'remark': f"截止日: {dl_dt}"
                }
        else:
            # Window Open (Past/Today)
            # Switch to Deadline View
            if dl_dt:
                if dl_dt <= future_dt or dl_dt < today:
                    days = (dl_dt - today).days
                    urgency = 'info'
                    if days < 0: urgency = 'danger'
                    elif days <= 7: urgency = 'danger'
                    elif days <= 30: urgency = 'warning'

                    urgency_text = f"剩 {days} 天"
                    if days < 0: urgency_text = f"已过期 {abs(days)} 天"

                    node_data = {
                        'date': dl_dt,
                        'type': 'deadline',
                        'type_name': '窗口期进行中',
                        'urgency': urgency,
                        'urgency_text': urgency_text,
                        'remark': f"窗口已于 {ws_dt} 开启"
                    }

    if node_data:
        full_node = node_data.copy()
        full_node.update({
            'subject_name': obj['subject_name'],
            'object_name': obj['object_name'],
            'category': f"{obj['object_type_main']} / {obj['object_type_sub']}",
            'is_locked': False,
            'object_id': obj['id']
        })
        nodes.append(full_node)

    return nodes

# 2. HTML Generation
def generate_html(nodes):
    rows = ""
    for node in nodes:
        badge_class = 'default'
        if node['type'] == 'window_start': badge_class = 'info'
        elif node['type'] == 'deadline': badge_class = 'danger'
        elif node['type'] == 'window_open': badge_class = 'success'

        # Color mapping for Urgency Row
        # dts_style.css approximation
        bg_style = ""
        if node['urgency'] == 'danger': bg_style = "background-color: #f2dede;"
        elif node['urgency'] == 'warning': bg_style = "background-color: #fcf8e3;"
        elif node['urgency'] == 'info': bg_style = "background-color: #d9edf7;"

        rows += f"""
        <tr style="{bg_style}">
            <td><strong>{node['date']}</strong></td>
            <td><span class="badge badge-{badge_class}">{node['type_name']}</span></td>
            <td><span class="urgency-badge urgency-{node['urgency']}">{node['urgency_text']}</span></td>
            <td>{node['subject_name']}</td>
            <td>
                <strong>{node['object_name']}</strong>
                <div class="text-muted small">{node['remark']}</div>
            </td>
            <td>{node['category']}</td>
            <td><button class="btn btn-xs btn-primary">查看</button></td>
        </tr>
        """

    html = f"""
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .badge-info {{ background-color: #5bc0de; }}
            .badge-danger {{ background-color: #d9534f; }}
            .badge-success {{ background-color: #5cb85c; }}
            .text-muted {{ color: #777; font-size: 85%; }}
            body {{ padding: 20px; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }}
        </style>
    </head>
    <body>
        <div class="card box-primary">
            <div class="card-header">
                <h3><i class="glyphicon glyphicon-bell"></i> 即将到来的节点</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>类型</th>
                            <th>紧急程度</th>
                            <th>主体</th>
                            <th>对象</th>
                            <th>分类</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows}
                    </tbody>
                </table>
            </div>
        </div>
    </body>
    </html>
    """
    return html

# 3. Execution & Screenshot
nodes = simulate_logic()
html_content = generate_html(nodes)

with open("simulation.html", "w") as f:
    f.write(html_content)

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page()
    page.goto(f"file://{os.getcwd()}/simulation.html")
    page.screenshot(path="dts_window_simulation.png")
    browser.close()

print("Simulation Complete. Screenshot saved to dts_window_simulation.png")
