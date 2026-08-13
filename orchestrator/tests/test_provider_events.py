from provider_events import ProviderEventType, ProviderEventBus, emit_provider_event


def test_provider_event_bus_emits_serializable_lifecycle_event():
    events = []
    bus = ProviderEventBus()
    bus.subscribe(events.append)

    event = ProviderEventType.SUBMITTED
    from provider_events import ProviderEvent

    event = ProviderEvent(
        event_type=event.value,
        job_id="job-1",
        provider_type="veo",
        connection_id=7,
        remote_job_ref="remote-1",
        status="processing",
        payload={"model_id": "veo-3.1-generate-preview"},
    )
    bus.publish(event)

    assert events == [event]
    assert event.as_dict()["event_type"] == "provider.submitted"
    assert event.as_dict()["connection_id"] == 7


def test_provider_event_bus_isolates_handler_failures():
    events = []
    bus = ProviderEventBus()

    def broken_handler(event):
        raise RuntimeError("handler failure")

    bus.subscribe(broken_handler)
    bus.subscribe(events.append)

    from provider_events import ProviderEvent

    bus.publish(
        ProviderEvent(
            event_type=ProviderEventType.COMPLETED.value,
            job_id="job-2",
            provider_type="nova_reel",
            status="completed",
        )
    )

    assert len(events) == 1
