"""Database acceptance checks; call only inside the verified disposable server."""
def check_fixture_install(root, sql, check):
    db = "demo3_seed_check"
    sql(f"CREATE DATABASE {db}")
    for name in ("schema.sql", "demo-fixtures.sql"):
        script = (root / "database" / name).read_text(encoding="utf-8-sig")
        assert "USE cst8319;" in script
        sql(script.replace("USE cst8319;", f"USE {db};"))
    check("packaged fixtures create both demo roles",
          sql(f"SELECT COUNT(*) FROM {db}.users WHERE role='admin'") == "1"
          and sql(f"SELECT COUNT(*) FROM {db}.users WHERE role='user'") == "2")
    check("fixture projector belongs to its category",
          sql(f"SELECT CONCAT(c.name, ':', r.name) FROM {db}.resources r "
              f"JOIN {db}.categories c ON c.category_id=r.category_id WHERE r.resource_id=1") == "Projectors:Demo projector")
    check("demo data has resources in every status",
          sql(f"SELECT COUNT(DISTINCT status) FROM {db}.resources") == "3"
          and sql(f"SELECT COUNT(*) FROM {db}.maintenance_notes WHERE ended_at IS NULL") == "1")

    sql(f"USE {db}; INSERT INTO reservations(user_id, resource_id, start_datetime, end_datetime, status) VALUES "
        "(1,1,NOW()-INTERVAL 2 DAY,NOW()-INTERVAL 1 DAY,'active'),"
        "(1,1,NOW()+INTERVAL 1 DAY,NOW()+INTERVAL 2 DAY,'active'),"
        "(1,1,NOW()+INTERVAL 2 DAY,NOW()+INTERVAL 3 DAY,'cancelled')")
    report = (root / "database/query.sql").read_text()
    section = report.split("-- QUERY 9", 1)[1].split("-- QUERY 10", 1)[0]
    statement = section[section.index("SELECT"):section.index(";")+1]
    rows = sql(f"USE {db}; " + statement).splitlines()
    check("actual upcoming report returns only the future active reservation",
          len(rows) == 1 and rows[0].split("\t")[0] == "2")
