import os
import pathlib
import sys


ROOT = pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT))


def pytest_configure(config):
    # Ensure Celery tasks run eagerly in tests by default
    try:
        from orchestrator import tasks

        tasks.celery_app.conf.task_always_eager = True
        # Avoid attempts to contact Redis during eager runs by replacing backend
        try:
            class _DummyBackend:
                def store_result(self, *a, **k):
                    return None

                def get(self, *a, **k):
                    return None

            tasks.celery_app.backend = _DummyBackend()
        except Exception:
            pass
    except Exception:
        # If orchestrator package can't be imported here, tests will load modules directly
        pass
