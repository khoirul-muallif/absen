**Purpose & context**

Khoirul is building `absensi-app`, a Laravel + Filament attendance management system. The project centers on a Filament admin panel covering approval workflows (Leave/Cuti, Permit/Izin, Overtime/Lembur, Official Travel/Dinas, Schedule Swap/TukarJadwal) and supporting modules (Leave Types/JenisCuti, Leave Quota/KuotaCuti). Development follows a structured, numbered audit methodology (phases), with Pest v4 as the testing framework.

Key project conventions:
- `todo.md` tracks only active items — completed items are deleted, not checked off
- `CHANGELOG.md` holds historical detail and serves as the authoritative record of prior decisions
- Tests use `Pest\Livewire\livewire()` helper and the `actingAsAdmin()` pattern from `tests/Pest.php`
- Fixes must be grounded in established project policies, citing specific phase numbers from the CHANGELOG rather than generic best practices

---

**Current state**

The structured audit has progressed through phase 25+, covering all major approval workflow modules. Several bugs were discovered and fixed during auditing (rather than pre-planned), including:

- `ViewCuti`, `ViewDinas`, and `ViewTukarJadwal` had unguarded `EditAction::make()` bypassing table-level approval restrictions — especially critical for Cuti, which triggers quota deduction and Absensi/Jadwal sync on approval
- `CutiForm` quota validation incorrectly rejected submissions when no `KuotaCuti` row existed (should be no-rejection per phase 22 policy)
- `TukarJadwalInfolist` was reading live relation data instead of snapshot columns, contradicting snapshot design from phases 11 and 24
- `TukarJadwalsTable` and Infolist badge only covered 3 of 5 actual enum statuses
- `JenisCutiForm` lacked unique validation on `nama`; `KuotaCutiForm` lacked application-level unique validation for `(karyawan_id, jenis_cuti_id, tahun)`

Key architectural decisions made:
- Lembur can span midnight as a single record; only zero-duration is rejected
- `alasan` on Lembur is nullable (new migration applied)
- `IzinsTable` edit restriction intentionally not applied — `jam_kembali` (return time) is nullable by design and must remain editable post-approval until a proper separate endpoint is built (documented as deliberate divergence)

---

**On the horizon**

- Cancel/force-override action for `TukarJadwal` stuck in `menunggu_rekan` status — deferred to backlog
- `KuotaCuti` to support 6-month (semester) periods in addition to annual — affects migration, unique constraint, and at least four query locations
- Continued numbered audit phases beyond phase 25

---

**Key learnings & principles**

- Approval actions on view pages must be guarded with `visible(fn ($record) => $record->isPending())` to prevent bypass of table-level restrictions; missing this is a security/data-integrity risk, not just a UX issue
- "No quota row = no basis for rejection" is the established policy (phase 22) — validation logic must not treat a missing row as zero balance
- Snapshot columns (not live relations) must be used in infolists for historical/audit integrity — reading live relation data contradicts this design
- Application-level unique validation should not be deferred solely to DB constraints, as raw `QueryException` is not user-friendly
- Intentional divergences from standard patterns must be explicitly documented in CHANGELOG with rationale

---

**Approach & patterns**

- Khoirul uploads actual source files; Claude audits them, proposes fixes grounded in prior CHANGELOG decisions, and Khoirul applies and runs tests
- When tests fail, output is shared and Claude diagnoses root cause before suggesting corrections
- Bugs found during audit are fixed immediately rather than deferred unless explicitly placed in backlog
- `todo.md` is kept strictly lean; completed items are removed entirely

---

**Tools & resources**

- Laravel + Filament (admin panel framework)
- Pest v4 (testing)
- `todo.md` and `CHANGELOG.md` as project memory/documentation artifacts