
# CDP Technical Test

**Author:** Mirana RAKOTONIRINA

Simplified Customer Data Platform API developed with PHP 8.4, PDO, MySQL 8.4 and Docker Compose.

The API provides:
- Event ingestion (`POST /api/events`)
- Customer profiles with statistics (`GET /api/customers/{id}`)
- Segmentation queries (`POST /api/segments/query`)

**Repository:** https://github.com/Amihrakotonirina/scale-cdp-test

---

## Prerequisites

Make sure the following tools are installed:

- Docker Engine
- Docker Compose v2

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/Amihrakotonirina/scale-cdp-test
cd scale-cdp-test
```
2. Build and start the containers
```bash

docker compose up -d --build
```
This command builds the PHP application image and starts the application and MySQL containers.

Wait until MySQL is healthy:
```bash

docker compose ps
```
The database container should display healthy in its status.
3. Install Composer dependencies
```bash

docker compose exec app composer install --no-dev --prefer-dist --no-interaction
```
This command generates the Composer autoloader and installs the project dependencies.

Important: The application directory is mounted from the host using .:/app. This mount hides the files generated inside /app during the image build, including vendor/autoload.php.

With the current configuration, Composer must therefore be run inside the container after the initial startup.
4. Verify the installation

Check that the autoloader exists:
```bash

docker compose exec app ls -la vendor/autoload.php
```
Check the container status:
```bash

docker compose ps
```
Accessing the application

Once the containers are running, the PHP development server is available at:

    Application: http://localhost:8080

    MySQL from the host: 127.0.0.1:3307

    MySQL from the application container: db:3306

The database connection is configured through environment variables in compose.yaml:
Variable	Value
DB_HOST	db
DB_PORT	3306
DB_NAME	cdp_test
DB_USER	cdp_user
DB_PASSWORD	cdp_dev_password

These are development credentials and should not be reused in production.
Development workflow

The application source code is mounted into the PHP container.

Changes made locally to PHP files are immediately visible inside the container. You do not need to rebuild the image after every code change.

When adding or changing Composer dependencies, run:
```bash

docker compose exec app composer install
```
Rebuild the application image only when changing the Dockerfile or when a rebuild is otherwise necessary:
```bash

docker compose up -d --build
```
Database initialization

The MySQL container uses a persistent Docker volume named cdp_mysql_data.

On the first initialization of an empty database volume, MySQL executes the SQL scripts mounted into /docker-entrypoint-initdb.d/:

    database/schema.sql

    database/seed.sql

These scripts are not automatically re-executed every time the container starts. Existing database volumes retain their data.
Useful commands
View running containers
```bash

docker compose ps
```
View application logs
```bash

docker compose logs -f app
```
View database logs
```bash

docker compose logs -f db
```
Open a shell in the application container
```bash

docker compose exec app sh
```
Open a MySQL shell inside the database container
```bash

docker compose exec db mysql -u root -p cdp_test
```
Stop the containers
```bash

docker compose down
```
This stops and removes the containers and the Compose network while preserving the database volume.
Reset the database and its data
```bash

docker compose down -v
docker compose up -d --build
```
Warning: The -v option deletes the Compose-managed database volume and its data. Use it only when a complete database reset is intended.

After resetting the environment, install Composer dependencies again if vendor/autoload.php is missing:
```bash

docker compose exec app composer install --no-dev --prefer-dist --no-interaction
```
API documentation

The API has 4 endpoints. All requests and responses use JSON.
1. Health check

GET /api/health

Check if the API is running.
```bash

curl http://localhost:8080/api/health
```
Response 200:
json

{"status": "API ok."}

2. Create an event

POST /api/events

Store a new customer event. If the customer email does not exist, it creates a new customer. If it exists, it updates the customer name.

Request body:
json

{
  "customer": {
    "email": "john@example.com",
    "name": "John Doe"
  },
  "event": "purchase",
  "properties": {
    "amount": 120,
    "product": "Shoes"
  },
  "timestamp": "2026-04-10T12:00:00Z"
}

Field rules:

    customer.email — required, valid email, max 254 chars. Lowercase is automatic.

    customer.name — required, max 200 chars.

    event — required, string, max 100 chars. Must start with a letter.

    properties — optional JSON object. Max 50 properties. Values must be int, float, string or bool. null is rejected. Max 1000 chars per string.

    timestamp — optional. ISO 8601 format. If missing, current UTC time is used.

Response 201:
json

{
  "message": "Event stored.",
  "customer_id": 1,
  "event_id": 42
}

Errors:

    422 — validation error (bad email, bad event name, bad property type, bad timestamp, body too big).

    500 — database error.

Example:
```bash

curl -X POST http://localhost:8080/api/events \
  -H 'Content-Type: application/json' \
  -d '{
    "customer": {"email": "john@example.com", "name": "John Doe"},
    "event": "purchase",
    "properties": {"amount": 120, "product": "Shoes"},
    "timestamp": "2026-04-10T12:00:00Z"
  }'
```
3. Get a customer profile

GET /api/customers/{id}

Return the customer profile, statistics, and last 10 events.
```bash

curl http://localhost:8080/api/customers/1
```
Response 200:
json

{
  "customer": {
    "id": 1,
    "email": "mirana@example.com",
    "name": "Mirana Rakotonirina",
    "created_at": "2026-04-01 09:00:00.000000",
    "updated_at": "2026-04-10 12:00:00.000000"
  },
  "statistics": {
    "total_events": 3,
    "total_purchases": 2,
    "total_spend": 195.5
  },
  "last_10_events": [
    {
      "id": 1003,
      "event_name": "purchase",
      "occurred_at": "2026-04-15 16:20:00.000000",
      "received_at": "2026-04-15 16:20:01.000000",
      "properties": {
        "amount": 75.5,
        "product": "Socks"
      }
    }
  ]
}

Important notes:

    total_events is the real total (all events). It is not the size of last_10_events.

    total_spend is the sum of amount for all purchase events.

    Property values keep their real type: amount is a number, product is a string, newsletter is a boolean.

    404 if the customer does not exist.

4. Query a segment

POST /api/segments/query

Find customers who match one or more conditions.

Request body:
json

{
  "conditions": [
    {
      "event": "purchase",
      "property": "amount",
      "operator": ">",
      "value": 100
    }
  ],
  "limit": 50,
  "offset": 0
}

Rules:

    conditions — required, non-empty list. Max 10 conditions. All conditions must match (AND).

    operator — one of =, !=, >, >=, <, <=.

    limit — optional, between 1 and 100. Default 50.

    offset — optional, >= 0. Default 0.

Response 200:
json

{
  "total": 3,
  "limit": 50,
  "offset": 0,
  "customers": [
    {"id": 1, "email": "mirana@example.com", "name": "Mirana Rakotonirina"},
    {"id": 2, "email": "anarim@example.com", "name": "Anarim Rinarakoto"},
    {"id": 5, "email": "john@example.com", "name": "John Doe"}
  ]
}

Important notes:

    total is the real total of matching customers. It does not change with limit or offset. This allows correct pagination.

    customers is only one page (max limit items).

    422 if conditions are invalid.

Example — one condition:
```bash

curl -X POST http://localhost:8080/api/segments/query \
  -H 'Content-Type: application/json' \
  -d '{"conditions":[{"event":"purchase","property":"amount","operator":">","value":100}]}'
```
Example — two conditions (AND):
```bash

curl -X POST http://localhost:8080/api/segments/query \
  -H 'Content-Type: application/json' \
  -d '{"conditions":[
    {"event":"purchase","property":"amount","operator":">","value":100},
    {"event":"purchase","property":"product","operator":"=","value":"Shoes"}
  ]}'
```
Example — pagination:
```bash

curl -X POST http://localhost:8080/api/segments/query \
  -H 'Content-Type: application/json' \
  -d '{"conditions":[{"event":"purchase","property":"amount","operator":">","value":100}],"limit":1,"offset":1}'
```
The response has total: 3, limit: 1, and only 1 customer in the page. This proves total is correct.
Design decisions

This section explains the main technical choices.
1. Segmentation uses JOIN, not EXISTS

First idea: one EXISTS subquery per condition.

This works, but it is slow on big data. With 10 conditions and 1M customers, MySQL can run 10 million subqueries.

Final choice: one INNER JOIN per condition, with the filters in the ON clause, followed by GROUP BY c.id.

MySQL can then start from the most selective JOIN and filter step by step. It uses the indexes idx_events_name_time_customer and idx_property_*_event.

COUNT(DISTINCT c.id) in a separate query returns the real total of matching customers. This is independent of LIMIT and OFFSET, so pagination is correct.

ORDER BY c.id is required. Without it, MySQL can return different pages between two calls.

Security: the aliases e{$i} / ep{$i} are loop indexes (0..9, checked by array_is_list). Operators are in a whitelist. Values are bound with PDO::bindValue. No SQL identifier comes from user input.
2. Customer profile: fewer queries and JSON in PHP

Before: 4 queries, including:

    2 scans of events (one for stats, one for spend);

    one correlated subquery JSON_OBJECTAGG run 10 times for the last 10 events.

After: 4 queries, but the last one is an IN on max 10 events (small cost):

    customers — primary key lookup.

    events LEFT JOIN event_properties — stats + spend in one pass. COUNT(DISTINCT) is needed because the LEFT JOIN multiplies rows (one event with 3 properties appears 3 times).

    events — last 10 events (no JSON_OBJECTAGG).

    event_properties WHERE event_id IN (...) — properties for the 10 events, in one query.

Why not JSON_OBJECTAGG?

MySQL serializes DECIMAL / DOUBLE as strings in JSON_OBJECTAGG on some setups (this is the documented behavior on MySQL 5.7). We tested several approaches in SQL:

    CAST(... AS DOUBLE) → still string.

    ep.value_number + 0 → still string.

    JSON_EXTRACT(...) → error 3146.

    CAST(... AS JSON) → still string.

So the JSON is built in PHP. We know value_type for each row, so the cast is explicit: (float) for number, (bool) for boolean, string for string.

Why this is good:

    Correct types guaranteed (float, bool, string).

    Works on MySQL 5.7, MySQL 8.x, and MariaDB. No JSON function dependency.

    Easy to test.

If you use MySQL 8.0+ only: JSON_OBJECTAGG + CAST(... AS DECIMAL(20,6)) works. But the PHP code is portable and does not need to change between MySQL versions.
3. Response is a value object with one exit point

Before: Response::json() did http_response_code, header, echo and exit. The return type was never. The controllers could not return a value, and the code was hard to test.

After:

    Response is an immutable value object (payload, status). No I/O.

    Response::json() returns self (testable).

    Response::send(): void writes the response (headers, echo, exit). It is the only exit in the project. It is called once, in public/index.php.

    Controllers and Router return a Response. They never call exit.

Benefits:

    Controllers and Router are easy to test without a web server.

    Two latent bugs are fixed:

        the Router catch blocks now run (before, exit stopped the process before catch).

        the controller Response is no longer lost (before, store() called exit itself, so the Router did not need return).

    One exit point makes it easy to add middlewares later (CORS, gzip, logging).

4. No never type (PHP 8.1)

never was removed on purpose:

    Compatibility: the project targets PHP 7 and 8.x. never only exists from 8.1.

    Consistency: the existing code does not use never. Adding it would split the style.

    Readability: never adds flow rules that are not always well known. void, Response, and an explicit exit in send() are simpler.

The separation "build / send" comes from the structure (a returned Response object), not from the type.
5. Input validation and security

All inputs are validated in EventValidator and SegmentService:

    Email: filter_var(FILTER_VALIDATE_EMAIL), max 254 chars, lowercase automatic.

    Event name and property name: regex ^[a-zA-Z][a-zA-Z0-9_.-]{0,99}$.

    Property values: must be scalar (int, float, string, bool). null, arrays and objects are rejected. Max 1000 chars per string.

    Timestamp: strict ISO 8601, stored in UTC with microseconds.

    Request body size: limited to 1 MB in Request::json().

    Segment operators: whitelist (=, !=, >, >=, <, <=).

    Segment values: typed and bound with PDO::bindValue and PDO::PARAM_*.

SQL identifiers are never built from user input. Table names and column names are hard-coded. User values are always bound parameters.
Assumed limits

This section lists what is not done, and why.
No customer_stats table

total_spend is a live SUM on each GET /api/customers/{id}. For a customer with 500 purchases, this reads and sums about 500 rows on each call.

Next step: a customer_stats table updated at ingestion (INSERT ... ON DUPLICATE KEY UPDATE). Not implemented here, to avoid a migration, a backfill, and a sync risk for a gain the test does not require.
No Redis cache

COUNT(DISTINCT c.id) in segmentation is costly on big data. A Redis cache (TTL 60s) or an aggregates table is recommended.

Next step: cache total by hash of conditions, invalidated at ingestion. Not implemented (no Redis in the test).
No idempotence

Re-sending the same event creates a duplicate (same customer, same event name, same properties). The "event deduplication" bonus is not implemented.

Idea: a natural key (customer_id, event_name, occurred_at, hash(properties)) with UNIQUE and INSERT IGNORE, or an event_hash computed at ingestion.
No authentication

Endpoints are open. No API key, no rate limiting.

Idea: a middleware api_key in Router::dispatch (header X-API-Key, lookup in DB), and rate limiting per IP (Redis INCR + EXPIRE).
No ingestion queue

POST /api/events is synchronous. For high traffic, a broker (Kafka, Redis Streams) with a worker is better.

Idea: write the event in a Redis Stream, a worker reads and inserts in batches.
Customer name is overwritten

The customer upsert does name = VALUES(name). If a customer sends a new event with a different name, the name is replaced.

This is a simple CDP policy: the latest source wins. For a real multi-source CDP, a finer policy is needed (priority by source, COALESCE, profile merge).
Strict timestamp validation

ISO 8601 strict: 2026-10-28T10:00:01Z, not 2026-10-28T10:00:010Z. Bad timestamps get a 422.

Note for manual tests: a loop for i in 1..15 with "timestamp":"...:0${i}Z" creates bad timestamps for i >= 10 (010, 011…). They are rejected. Use printf '%02d' $i.
No PHPUnit tests yet

The code is testable (thanks to the Response value object), but no PHPUnit suite is included.

Next step: unit tests for controllers, validator and Response; integration tests for the HTTP layer with php -S and curl.
Future improvements

By priority (value / effort):

    PHPUnit tests — unit (controllers, validator, Response) and integration (HTTP, dedicated DB). High priority: controllers are now testable.

    customer_stats table — pre-computed aggregates, updated at ingestion. Backfill script.

    Redis cache — for COUNT(DISTINCT) in segmentation. TTL 60s, invalidated at ingestion.

    Idempotence — event_hash + UNIQUE, INSERT IGNORE or app-level dedup.

    Auth + rate limiting — API key, quota per source.

    Ingestion queue — Redis Streams + batch worker.

    CI/CD — GitHub Actions: composer validate, php -l, PHPUnit, docker compose up.

    Observability — structured logs (JSON), metrics (Prometheus), tracing.

Project structure
text

scale-cdp-test/
├── compose.yaml              # MySQL 8.4 + PHP 8.4 Apache
├── Dockerfile
├── .env.example
├── composer.json
├── database/
│   ├── schema.sql            # customers, events, event_properties
│   └── seed.sql              # demo data
├── public/
│   └── index.php             # front controller (only exit via Response::send)
├── src/
│   ├── Controllers/          # Event, Customer, Segment (return Response)
│   ├── Core/                 # Database, Request, Response, Router
│   ├── Repositories/         # Customer, Event
│   ├── Services/             # Customer, Event, Segment
│   └── Validators/           # EventValidator
└── README.md

Manual tests (smoke tests)

Use these commands to check the API quickly.
```bash

# 1. Health (200)
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/api/health

# 2. Create event (201)
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8080/api/events \
  -H 'Content-Type: application/json' \
  -d '{"customer":{"email":"smoke@test.com","name":"Smoke"},"event":"purchase","properties":{"amount":10},"timestamp":"2026-04-10T12:00:00Z"}'

# 3. Get profile (200)
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/api/customers/1

# 4. Get profile of missing customer (404)
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/api/customers/99999

# 5. Segment query (200)
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8080/api/segments/query \
  -H 'Content-Type: application/json' \
  -d '{"conditions":[{"event":"purchase","property":"amount","operator":">","value":100}]}'

# 6. Segment query with empty conditions (422)
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8080/api/segments/query \
  -H 'Content-Type: application/json' -d '{"conditions":[]}'
```
Expected output:
text

200
201
200
404
200
422

If all 6 codes match, the API works correctly.
Example workflow

A full example: create a customer, create events, then query a segment.
```bash

# 1. Create a first event (creates the customer)
curl -s -X POST http://localhost:8080/api/events \
  -H 'Content-Type: application/json' \
  -d '{
    "customer": {"email": "alice@example.com", "name": "Alice"},
    "event": "purchase",
    "properties": {"amount": 150, "product": "Shoes"},
    "timestamp": "2026-10-28T10:00:00Z"
  }'
# → {"message":"Event stored.","customer_id":11,"event_id":42}

# 2. Create a second event for the same customer
curl -s -X POST http://localhost:8080/api/events \
  -H 'Content-Type: application/json' \
  -d '{
    "customer": {"email": "alice@example.com", "name": "Alice"},
    "event": "purchase",
    "properties": {"amount": 50, "product": "Socks"},
    "timestamp": "2026-10-28T11:00:00Z"
  }'

# 3. Get the profile (customer_id = 11)
curl -s http://localhost:8080/api/customers/11 | python3 -m json.tool
# → total_events: 2, total_purchases: 2, total_spend: 200.0
# → last_10_events: [purchase 50, purchase 150]

# 4. Segment query: customers with purchase amount > 100
curl -s -X POST http://localhost:8080/api/segments/query \
  -H 'Content-Type: application/json' \
  -d '{"conditions":[{"event":"purchase","property":"amount","operator":">","value":100}]}' \
  | python3 -m json.tool
# → total: 1, customers: [alice@example.com]
```
License

Technical test — Scal-e internal use only.