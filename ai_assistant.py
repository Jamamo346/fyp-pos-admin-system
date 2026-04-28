#!/usr/bin/env python3
import json
import os
import re
import sys
from datetime import datetime

# ==DATABASE CONNECTION ==# 
def get_connection():
    db_host = os.environ.get("DB_HOST", "phpmyadmin.ecs.westminster.ac.uk")
    db_name = os.environ.get("DB_NAME", "w1987586_0")
    db_user = os.environ.get("DB_USER", "w1987586")
    db_pass = os.environ.get("DB_PASS", "")

    # Try mysql.connector first
    try:
        import mysql.connector  # type: ignore
        conn = mysql.connector.connect(
            host=db_host,
            user=db_user,
            password=db_pass,
            database=db_name
        )
        return conn, "mysql.connector"
    except Exception:
        pass

    # Fallback to pymysql
    try:
        import pymysql  # type: ignore
        conn = pymysql.connect(
            host=db_host,
            user=db_user,
            password=db_pass,
            database=db_name,
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor
        )
        return conn, "pymysql"
    except Exception as e:
        raise RuntimeError(f"Database connection failed: {e}")
# =========================
# QUERY HELPERS
# =========================
def fetch_one(conn, query, params=None):
    """Execute a query and return the first row."""
    cursor = conn.cursor()
    cursor.execute(query, params or ())
    row = cursor.fetchone()
    cursor.close()
    return row


def fetch_all(conn, query, params=None):
    """Execute a query and return all rows."""
    cursor = conn.cursor()
    cursor.execute(query, params or ())
    rows = cursor.fetchall()
    cursor.close()
    return rows


def val(row, index_or_key):
    """Extract a value from a row (works with tuples and dicts)."""
    if row is None:
        return None
    if isinstance(row, dict):
        return row.get(index_or_key)
    if isinstance(row, (tuple, list)):
        idx = index_or_key if isinstance(index_or_key, int) else 0
        return row[idx] if idx < len(row) else None
    return None


# =========================
# FORMATTING HELPERS
# =========================
def normalise(text: str) -> str:
    return re.sub(r"\s+", " ", text.strip().lower())


def money(value) -> str:
    try:
        return f"\u00a3{float(value):,.2f}"
    except Exception:
        return "\u00a30.00"


def role_can_reports(role: str) -> bool:
    return role in ("Administrator", "Manager")


def role_can_users(role: str) -> bool:
    return role == "Administrator"


# =========================
# INTENT MATCHING & ANSWERS
# =========================
def answer_question(conn, question: str, role: str, username: str) -> str:
    q = normalise(question)

    if not q:
        return "Please enter a question."

    # ---------- Greeting ----------
    if any(w in q for w in ("hello", "hi ", "hey", "good morning", "good afternoon")):
        return (
            f"Hello {username}! I'm your Sales Intelligence Assistant. "
            "Ask me about revenue, transactions, products, stock levels, or staff performance."
        )

    # ---------- Today's revenue ----------
    if "today" in q and ("revenue" in q or "sales" in q or "sale" in q):
        row = fetch_one(conn, """
            SELECT COALESCE(SUM(amount), 0) AS v
            FROM transactions
            WHERE DATE(created_at) = CURDATE()
        """)
        return f"Today's total revenue is **{money(val(row, 'v') or val(row, 0))}**."

    # ---------- Today's transaction count ----------
    if "today" in q and "transaction" in q:
        row = fetch_one(conn, """
            SELECT COUNT(*) AS v
            FROM transactions
            WHERE DATE(created_at) = CURDATE()
        """)
        return f"There have been **{int(val(row, 'v') or val(row, 0))}** transaction(s) today."

    # ---------- Total revenue ----------
    if "total revenue" in q or ("overall" in q and "revenue" in q):
        if not role_can_reports(role):
            return "You do not have permission to access revenue analytics."
        row = fetch_one(conn, "SELECT COALESCE(SUM(amount), 0) AS v FROM transactions")
        return f"The overall total revenue is **{money(val(row, 'v') or val(row, 0))}**."

    # ---------- Highest transaction ----------
    if "highest transaction" in q or "largest transaction" in q or "biggest transaction" in q:
        if not role_can_reports(role):
            return "You do not have permission to access transaction analytics."
        row = fetch_one(conn, "SELECT COALESCE(MAX(amount), 0) AS v FROM transactions")
        return f"The highest transaction recorded is **{money(val(row, 'v') or val(row, 0))}**."

    # ---------- Average transaction ----------
    if "average transaction" in q:
        if not role_can_reports(role):
            return "You do not have permission to access transaction analytics."
        row = fetch_one(conn, "SELECT COALESCE(AVG(amount), 0) AS v FROM transactions")
        return f"The average transaction value is **{money(val(row, 'v') or val(row, 0))}**."

    # ---------- Most popular product ----------
    if any(p in q for p in ("most popular product", "top product", "best selling", "popular product")):
        row = fetch_one(conn, """
            SELECT product_type, COUNT(*) AS cnt
            FROM transactions
            WHERE product_type IS NOT NULL AND product_type <> ''
            GROUP BY product_type
            ORDER BY cnt DESC, product_type ASC
            LIMIT 1
        """)
        if not row:
            return "No transaction data available yet for product analysis."

        name = val(row, "product_type") or val(row, 0)
        cnt  = val(row, "cnt") or val(row, 1)
        return f"The most popular product is **{name}**, with **{int(cnt)}** recorded sale(s)."

    # ---------- Top performing admin ----------
    if any(p in q for p in ("top admin", "best admin", "top performing", "highest sales", "top staff", "best staff")):
        if not role_can_reports(role):
            return "You do not have permission to access admin performance analytics."
        row = fetch_one(conn, """
            SELECT admin_username, COUNT(*) AS cnt
            FROM transactions
            GROUP BY admin_username
            ORDER BY cnt DESC, admin_username ASC
            LIMIT 1
        """)
        if not row:
            return "No transaction data available yet for admin performance analysis."

        name = val(row, "admin_username") or val(row, 0)
        cnt  = val(row, "cnt") or val(row, 1)
        return f"The top performing admin is **{name}** with **{int(cnt)}** order(s)."

    # ---------- Low stock ----------
    if "low stock" in q:
        rows = fetch_all(conn, """
            SELECT prodName, prodQuantity
            FROM Product
            WHERE prodQuantity BETWEEN 1 AND 10
            ORDER BY prodQuantity ASC, prodName ASC
            LIMIT 10
        """)
        if not rows:
            return "There are currently no low stock items."

        parts = []
        for row in rows:
            name = val(row, "prodName") or val(row, 0)
            qty  = val(row, "prodQuantity") or val(row, 1)
            parts.append(f"{name} ({int(qty)} left)")
        return "**Low stock items:**\n" + "\n".join(f"• {p}" for p in parts)

    # ---------- Out of stock ----------
    if "out of stock" in q:
        rows = fetch_all(conn, """
            SELECT prodName
            FROM Product
            WHERE prodQuantity = 0
            ORDER BY prodName ASC
            LIMIT 10
        """)
        if not rows:
            return "All products are currently in stock!"

        parts = [val(row, "prodName") or val(row, 0) for row in rows]
        return "**Out of stock items:**\n" + "\n".join(f"• {p}" for p in parts)

    # ---------- Total products ----------
    if "total products" in q or "how many products" in q:
        row = fetch_one(conn, "SELECT COUNT(*) AS v FROM Product")
        return f"There are **{int(val(row, 'v') or val(row, 0))}** product(s) in the inventory."

    # ---------- Total transactions ----------
    if "total transaction" in q or "how many transaction" in q:
        row = fetch_one(conn, "SELECT COUNT(*) AS v FROM transactions")
        return f"There have been **{int(val(row, 'v') or val(row, 0))}** transaction(s) in total."

    # ---------- Active users ----------
    if "active user" in q:
        if not role_can_users(role):
            return "You do not have permission to access user activity analytics."
        row = fetch_one(conn, "SELECT COUNT(*) AS v FROM admins WHERE last_login IS NOT NULL")
        return f"There are **{int(val(row, 'v') or val(row, 0))}** active staff user(s)."

    # ---------- Role counts ----------
    if "how many managers" in q:
        if not role_can_users(role):
            return "You do not have permission to access user role analytics."
        row = fetch_one(conn, "SELECT COUNT(*) AS v FROM admins WHERE role = 'Manager'")
        return f"There are **{int(val(row, 'v') or val(row, 0))}** manager account(s)."

    if "how many cashiers" in q:
        if not role_can_users(role):
            return "You do not have permission to access user role analytics."
        row = fetch_one(conn, "SELECT COUNT(*) AS v FROM admins WHERE role = 'Cashier'")
        return f"There are **{int(val(row, 'v') or val(row, 0))}** cashier account(s)."

    if "how many admin" in q:
        if not role_can_users(role):
            return "You do not have permission to access user role analytics."
        row = fetch_one(conn, "SELECT COUNT(*) AS v FROM admins WHERE role = 'Administrator'")
        return f"There are **{int(val(row, 'v') or val(row, 0))}** administrator account(s)."

    # ---------- Revenue by terminal ----------
    if "terminal" in q and "revenue" in q:
        if not role_can_reports(role):
            return "You do not have permission to access terminal revenue analytics."
        rows = fetch_all(conn, """
            SELECT terminal, COALESCE(SUM(amount), 0) AS rev
            FROM transactions
            GROUP BY terminal
            ORDER BY rev DESC, terminal ASC
            LIMIT 5
        """)
        if not rows:
            return "No terminal revenue data available yet."

        parts = []
        for row in rows:
            t = val(row, "terminal") or val(row, 0)
            r = val(row, "rev") or val(row, 1)
            parts.append(f"• {t}: {money(r)}")
        return "**Revenue by terminal:**\n" + "\n".join(parts)
    
        # ---------- Supplier access guard ----------
    if "supplier" in q or "suppliers" in q:
        if not role_can_users(role):
            return "You do not have permission to access supplier analytics."
        
            # ---------- Supplier help ----------
    if q == "supplier" or q == "suppliers" or "supplier help" in q:
        return (
            "Supplier commands available:\n"
            "• How many suppliers do we have?\n"
            "• Who is the top supplier?\n"
            "• Show supplier balances\n"
            "• Which suppliers are in debt?\n"
            "• Which suppliers have credit?\n"
            "• What was the last supplier purchase?"
        )

    # ---------- Total suppliers ----------
    if "how many suppliers" in q or "total suppliers" in q:
        row = fetch_one(conn, "SELECT COUNT(*) AS v FROM suppliers")
        return f"There are **{int(val(row, 'v') or val(row, 0))}** supplier(s) in the system."

    # ---------- Top supplier ----------
    if "top supplier" in q or "best supplier" in q or "highest supplier purchases" in q:
        row = fetch_one(conn, """
            SELECT company_name, total_purchases
            FROM suppliers
            ORDER BY total_purchases DESC, company_name ASC
            LIMIT 1
        """)
        if not row:
            return "No supplier data is available yet."

        name = val(row, "company_name") or val(row, 0)
        purchases = val(row, "total_purchases") or val(row, 1)
        return f"The top supplier is **{name}** with **{int(purchases)}** total purchase record(s)."

    # ---------- Supplier balances ----------
    if "supplier balance" in q or "supplier balances" in q or "outstanding suppliers" in q:
        rows = fetch_all(conn, """
            SELECT company_name, balance, balance_type
            FROM suppliers
            WHERE balance > 0
            ORDER BY balance DESC, company_name ASC
            LIMIT 10
        """)
        if not rows:
            return "All supplier balances are currently settled."

        parts = []
        for row in rows:
            name = val(row, "company_name") or val(row, 0)
            balance = val(row, "balance") or val(row, 1)
            balance_type = val(row, "balance_type") or val(row, 2)
            parts.append(f"{name} ({money(balance)} - {balance_type})")

        return "**Supplier balances:**\n" + "\n".join(f"• {p}" for p in parts)

    # ---------- Suppliers with debt ----------
    if "suppliers in debt" in q or "who is in debt" in q or "debt suppliers" in q:
        rows = fetch_all(conn, """
            SELECT company_name, balance
            FROM suppliers
            WHERE balance_type = 'Debt'
            ORDER BY balance DESC, company_name ASC
            LIMIT 10
        """)
        if not rows:
            return "There are no suppliers currently marked as debt."

        parts = []
        for row in rows:
            name = val(row, "company_name") or val(row, 0)
            balance = val(row, "balance") or val(row, 1)
            parts.append(f"{name} ({money(balance)})")

        return "**Suppliers in debt:**\n" + "\n".join(f"• {p}" for p in parts)

    # ---------- Suppliers with credit ----------
    if "suppliers with credit" in q or "credit suppliers" in q:
        rows = fetch_all(conn, """
            SELECT company_name, balance
            FROM suppliers
            WHERE balance_type = 'Credit'
            ORDER BY balance DESC, company_name ASC
            LIMIT 10
        """)
        if not rows:
            return "There are no suppliers currently marked as credit."

        parts = []
        for row in rows:
            name = val(row, "company_name") or val(row, 0)
            balance = val(row, "balance") or val(row, 1)
            parts.append(f"{name} ({money(balance)})")

        return "**Suppliers with credit:**\n" + "\n".join(f"• {p}" for p in parts)

    # ---------- Most recent supplier purchase ----------
    if "last supplier purchase" in q or "latest supplier purchase" in q:
        row = fetch_one(conn, """
            SELECT company_name, last_purchase
            FROM suppliers
            WHERE last_purchase IS NOT NULL
            ORDER BY last_purchase DESC, company_name ASC
            LIMIT 1
        """)
        if not row:
            return "No supplier purchase date is available yet."

        name = val(row, "company_name") or val(row, 0)
        last_purchase = val(row, "last_purchase") or val(row, 1)
        return f"The most recent supplier purchase was from **{name}** on **{last_purchase}**."
    

    # ---------- Fallback ----------
    return (
        "I could not understand that question yet. Try asking:\n"
        "• What is today's total revenue?\n"
        "• How many transactions were made today?\n"
        "• What is the most popular product?\n"
        "• Who is the top performing admin?\n"
        "• What items are low in stock?\n"
        "• What is the average transaction value?"
    )


# =========================
# MAIN ENTRY POINT
# =========================
def main():
    try:
        raw = sys.stdin.read().strip()
        if not raw:
            raise ValueError("No input received from PHP handler.")

        payload  = json.loads(raw)
        question = str(payload.get("question", "")).strip()
        role     = str(payload.get("role", "")).strip()
        username = str(payload.get("username", "User")).strip()

        conn, _ = get_connection()
        answer   = answer_question(conn, question, role, username)
        conn.close()

        print(json.dumps({
            "success":   True,
            "answer":    answer,
            "source":    "python",
            "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }))

    except Exception as e:
        print(json.dumps({
            "success": False,
            "answer":  f"AI assistant error: {str(e)}"
        }))


if __name__ == "__main__":
    main()