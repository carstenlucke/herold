# SQLite as Embedded Persistence -- Variant Comparison

## Context

Herold needs durable storage for one operator, voice-note state, sessions, and outbound issue references. Production is shared hosting with no budget or operational surface for a separate database server. The specification therefore requires a low-footprint database but does not prescribe a database product.

Audio blobs remain in the private filesystem; this decision concerns relational application data.

---

## Option 1: Server Database (PostgreSQL or MySQL)

**Pros:**
- Strong concurrent-write behaviour
- Mature remote administration and replication options
- Familiar scaling path

**Cons:**
- Requires a separately provisioned service, credentials, and backup lifecycle
- Adds operational dependencies to a single-user application
- Exceeds the low-footprint constraint and available hosting assumptions

---

## Option 2: Application-Managed JSON or Flat Files

**Pros:**
- No database engine
- Files are easy to inspect and copy

**Cons:**
- The application must implement locking, transactions, indexing, and schema evolution
- Cross-record consistency and concurrent requests become application concerns
- Laravel's migration and Eloquent support would be discarded

---

## Option 3: SQLite

**Pros:**
- Embedded in the PHP process; no database server or database credentials
- Relational constraints, transactions, indexes, and migrations
- One portable persistent file
- Native Laravel/Eloquent support
- Sufficient for the single-operator write load

**Cons:**
- Concurrent writes serialize at file level
- Durability depends on the hosting filesystem and operator backups
- The database file must be excluded carefully from deployment mirroring

---

## Decision: Option 3 -- SQLite

SQLite is Herold's sole application database. The production database is a persistent file at `database/data/database.sqlite`; development bind-mounts the corresponding directory. Schema changes are delivered as Laravel migrations.

## Rationale

1. SQLite satisfies the mandated low-footprint database constraint without recreating database behaviour in application code.
2. One operator generates minimal concurrent write load, so a server database would add operations without meaningful benefit.
3. Laravel provides first-class SQLite support for Eloquent, migrations, sessions, cache, and tests.
4. The single-file form matches the host's persistent file surfaces and makes out-of-band backup possible.

## Consequences

- No database server, network connection, or database credential is required.
- Foreign keys and schema constraints remain enabled. The single-operator invariant is enforced additionally by the `enforce_users_singleton` database trigger, so it survives new application call paths.
- Journal and transaction settings use Laravel/SQLite defaults unless operational evidence justifies an explicit override; no WAL mode is assumed by the architecture.
- The release mirror must never overwrite or delete `database/data/`.
- Production migrations are operator-run via `php artisan migrate --force`; they are not executed in the HTTP request path or deployment workflow.
- Backups must cover the SQLite database together with audio and `.env`; `scripts/backup.sh` is the current operator tool.
- Higher write concurrency, multi-user operation, or filesystem-locking problems would trigger reconsideration of this ADR.
