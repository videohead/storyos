# Tests

How to run unit tests for the orchestrator.

## Prerequisites

- Lando installed and running (`lando start`)

## Run tests via `lando ssh`

SSH into the app container first, then run pytest:

```bash
lando ssh -s app
pip install -r /orchestrator/requirements.txt
cd /orchestrator && pytest tests -q
```

To run a specific test file:

```bash
cd /orchestrator && pytest tests/test_tasks.py -v
```

To run with verbose output and show tracebacks:

```bash
cd /orchestrator && pytest tests -v --tb=short
```

To run with coverage:

```bash
pip install pytest-cov
cd /orchestrator && pytest tests --cov=orchestrator --cov-report=term-missing
```


