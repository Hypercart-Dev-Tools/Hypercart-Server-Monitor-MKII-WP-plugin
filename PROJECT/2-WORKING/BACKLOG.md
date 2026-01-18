# BACKLOG

## P1 - LAUNCH CHECKLIST
- [ ] Confirm plugin version and changelog updated for release.
- [ ] Deploy to staging and run a manual monitoring cycle.
- [ ] Verify cron schedule exists and next run time is visible.
- [ ] Confirm lock acquisition/release logs appear as expected.
- [ ] Verify data directory permissions and `health-data.json` writes.
- [ ] Validate admin dashboard renders and sample chart loads.
- [ ] Send a test email and confirm delivery.
- [ ] Check circuit breaker state remains idle after a clean run.
- [ ] Monitor logs for 24 hours and ensure pruning keeps file size bounded.
- [ ] Back up production and schedule a low-traffic deploy window.
