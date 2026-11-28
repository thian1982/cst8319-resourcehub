"""Exercise the real PHP booking endpoints with disposable Windows XAMPP services."""
import concurrent.futures
import contextlib
import datetime as dt
import http.cookiejar
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess as sp
import tempfile
import threading
import time
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
XAMPP = Path(os.environ.get("XAMPP_ROOT", "C:/xampp"))
BIN = XAMPP / "mysql/bin"
PHP = str(XAMPP / "php/php.exe")


def port():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def stop(proc):
    if proc.poll() is None:
        proc.terminate()
    proc.wait(timeout=15)


def ready(proc, number):
    for _ in range(150):
        if proc.poll() is not None:
            raise RuntimeError("Test server exited during startup")
        try:
            with socket.create_connection(("127.0.0.1", number), timeout=.2):
                return
        except OSError:
            time.sleep(.1)
    raise RuntimeError("Test server did not start")


def client():
    return urllib.request.build_opener(urllib.request.ProxyHandler({}),
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def page(opener, base, path, form=None):
    data = urllib.parse.urlencode(form).encode() if form is not None else None
    with opener.open(base + path, data, timeout=15) as response:
        return response.read().decode()


def token(html):
    return re.search(r'name="csrf_token" value="([^"]+)"', html)[1]


def check(label, condition):
    if not condition:
        raise AssertionError(label)
    print("PASS:", label, flush=True)


def main():
    with tempfile.TemporaryDirectory(prefix="rh-demo3-") as folder, contextlib.ExitStack() as cleanup:
        tmp = Path(folder)
        data = tmp / "mysql"
        dbport = port()
        secret = secrets.token_hex(16)
        sp.run([BIN / "mysql_install_db.exe", f"--datadir={data}", f"--password={secret}",
                f"--port={dbport}"], check=True, stdout=sp.DEVNULL, cwd=tmp)
        db = sp.Popen([BIN / "mysqld.exe", "--no-defaults", f"--basedir={XAMPP/'mysql'}",
                       f"--datadir={data}", "--bind-address=127.0.0.1", f"--port={dbport}"],
                      cwd=tmp, stdout=sp.DEVNULL, stderr=sp.DEVNULL)
        cleanup.callback(stop, db)
        ready(db, dbport)
        dbcmd = [BIN / "mysql.exe", "--no-defaults", "--protocol=tcp", "-h127.0.0.1",
                 f"-P{dbport}", "-uroot", "-N", "-B"]

        def sql(statement):
            return sp.run(dbcmd, input=statement, text=True, capture_output=True,
                          env={**os.environ, "MYSQL_PWD": secret}, check=True).stdout.strip()

        # Verify the server identity BEFORE any DDL, even though we use a random port/password.
        check("database belongs to this temporary directory", Path(sql("SELECT @@datadir")).resolve() == data.resolve())
        sql("CREATE DATABASE cst8319;" + (ROOT/"database/schema.sql").read_text())
        hashed = sp.check_output([PHP, "-n", "-r", "echo password_hash('fixture-pass', PASSWORD_DEFAULT);"], text=True)
        sql("USE cst8319; INSERT INTO categories(name) VALUES ('Projectors');"
            "INSERT INTO resources(category_id,name,location) VALUES(1,'Fixture projector','Lab');"
            "INSERT INTO users(full_name,email,password_hash) VALUES " +
            ",".join(f"('User {i}','u{i}@example.test','{hashed}')" for i in (1,2)) + ";")

        # Copy only public application source plus the non-secret loader. Never copy config/.
        for pattern in ("*.php", "*.css"):
            for src in (ROOT/"public").rglob(pattern):
                dest = tmp / src.relative_to(ROOT)
                dest.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(src, dest)
        shutil.copytree(ROOT/"src", tmp/"src")
        (tmp/"config").mkdir()
        shutil.copyfile(ROOT/"config/database.php", tmp/"config/database.php")
        (tmp/"config/database.local.php").write_text(
            f"<?php return ['host'=>'127.0.0.1','port'=>{dbport},'database'=>'cst8319',"
            f"'username'=>'root','password'=>'{secret}','charset'=>'utf8mb4'];")
        bases = []
        for _ in range(2):
            n = port()
            proc = sp.Popen([PHP, "-d", f"session.save_path={tmp}", "-S", f"127.0.0.1:{n}",
                             "-t", str(tmp/"public")], cwd=tmp, stdout=sp.DEVNULL, stderr=sp.DEVNULL)
            cleanup.callback(stop, proc)
            ready(proc, n)
            bases.append(f"http://127.0.0.1:{n}")
        users = [client(), client()]
        tokens = []
        detail = "/resource-details.php?id=1"
        for i, (user, base) in enumerate(zip(users, bases), 1):
            login = page(user, base, "/login.php")
            home = page(user, base, "/login.php", {"email":f"u{i}@example.test","password":"fixture-pass",
                                                   "csrf_token":token(login)})
            check(f"user {i} authenticated", "Logout" in home)
            tokens.append(token(page(user, base, detail)))
        day = str(dt.date.today()+dt.timedelta(days=7))
        def book(i, start, end, action="reserve"):
            return page(users[i], bases[i], detail, dict(start_date=day, end_date=day,
                start_time=start, end_time=end, booking_action=action, csrf_token=tokens[i]))
        def count(where="1"):
            return int(sql("USE cst8319; SELECT COUNT(*) FROM reservations WHERE "+where))
        check("availability lookup creates no reservation", "Available!" in book(0,"10:00","11:00","check") and count()==0)
        check("valid booking persisted", "Reservation confirmed." in book(0,"10:00","11:00") and count()==1)
        check("overlap rejected", "already reserved" in book(1,"10:30","11:30") and count()==1)
        check("back-to-back accepted", "Reservation confirmed." in book(1,"11:00","12:00") and count()==2)
        check("invalid interval rejected", "end must be after" in book(0,"14:00","13:00") and count()==2)
        history = "/my-reservations.php"
        def cancel(i):
            return page(users[i], bases[i], history, dict(csrf_token=tokens[i], action="cancel", reservation_id=1))
        check("other user cannot cancel", "cannot be cancelled" in cancel(1) and count("status='active'")==2)
        check("owner cancellation retained in history", "Reservation cancelled successfully" in cancel(0)
              and count("reservation_id=1 AND status='cancelled' AND cancelled_at IS NOT NULL")==1)
        check("released slot can be rebooked", "Reservation confirmed." in book(1,"10:00","11:00"))
        barrier=threading.Barrier(2)
        def race(i):
            barrier.wait(timeout=5)
            return book(i,"14:00","15:00")
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            outcomes=list(pool.map(race,[0,1]))
        check("concurrent requests: one success, one conflict, one active row",
              sum("Reservation confirmed." in r for r in outcomes)==1 and
              sum("already reserved" in r for r in outcomes)==1 and
              count(f"start_datetime='{day} 14:00:00' AND status='active'")==1)
        sql("USE cst8319; INSERT INTO reservations(user_id,resource_id,start_datetime,end_datetime) "
            "VALUES(1,1,NOW()-INTERVAL 2 DAY,NOW()-INTERVAL 1 DAY)")
        check("expired active booking counts as completed on both pages",
              "Completed</div><strong>1</strong>" in page(users[0],bases[0],"/profile.php") and
              'data-status="Completed"' in page(users[0],bases[0],history))
        home = page(users[0],bases[0],"/")
        check("homepage renders database category, resource and real detail link",
              "Fixture projector" in home and "resource-details.php?id=1" in home and
              "resources.php?category=Projectors" in home and "Sample catalogue" not in home)
        catalogue = page(users[0],bases[0],"/resources.php?category=Projectors&q=Fixture")
        check("catalogue receives homepage query and category",
              'value="Fixture"' in catalogue and 'value="Projectors" selected' in catalogue and 'id="statusSelect"' in catalogue)
        sql(f"USE cst8319; INSERT INTO users(full_name,email,password_hash,role) VALUES('Admin','admin@example.test','{hashed}','admin')")
        admin=client()
        page(admin,bases[0],"/login.php",dict(csrf_token=token(page(admin,bases[0],"/login.php")),
             email="admin@example.test",password="fixture-pass"))
        def admin_action(module, **fields):
            path="/admin/admin-"+module+".php"
            csrf=token(page(admin,bases[0],path))
            return page(admin,bases[0],path,dict(csrf_token=csrf,**fields))
        admin_action("categories",action="add",name="Test tools",description="Fixture")
        admin_action("resources",name="Fixture drill",category_id=2,location="Test room",status="available")
        check("admin creates category and resource",sql("SELECT COUNT(*) FROM cst8319.resources WHERE category_id=2 AND name='Fixture drill'")=="1")
        admin_action("categories",action="remove",category_id=2)
        check("assigned category protected",sql("SELECT COUNT(*) FROM cst8319.categories WHERE category_id=2")=="1")
        admin_action("categories",action="add",name="Unused category",description="")
        admin_action("categories",action="remove",category_id=3)
        check("unused category removable",sql("SELECT COUNT(*) FROM cst8319.categories WHERE category_id=3")=="0")
        admin_action("users",action="create",full_name="Test account",email="new@example.test",
                     role="user",account_status="active",password="fixture-password")
        check("admin creates account",sql("SELECT COUNT(*) FROM cst8319.users WHERE email='new@example.test'")=="1")
        admin_action("maintenance",action="create",resource_id=2,note="Inspect equipment")
        check("maintenance blocks bookings",sql("SELECT status FROM cst8319.resources WHERE resource_id=2")=="maintenance" and
              'cannot be reserved' in page(users[0],bases[0],"/resource-details.php?id=2"))
        admin_action("resources",resource_id=2,name="Fixture drill",category_id=2,location="Test room",status="available")
        check("editor cannot bypass open maintenance",sql("SELECT status FROM cst8319.resources WHERE resource_id=2")=="maintenance")
        admin_action("maintenance",action="complete",maintenance_note_id=1)
        check("completed repair restores availability",sql("SELECT status FROM cst8319.resources WHERE resource_id=2")=="available")
        admin_action("reservations",action="create",resource_id=2,user_id=1,
                     start_datetime=day+"T16:00",end_datetime=day+"T17:00")
        check("admin books available resource", count("resource_id=2 AND status='active'")==1)
        rid=sql("SELECT reservation_id FROM cst8319.reservations WHERE resource_id=2")
        admin_action("reservations",action="cancel",reservation_id=rid)
        check("admin cancels booking",count("resource_id=2 AND status='cancelled'")==1)



if __name__ == "__main__":
    main()
