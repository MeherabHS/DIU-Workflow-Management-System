# Known Limitations — DIUS Management Portal

These are documented limitations of the current MVP. They are not bugs but intentional trade-offs or deferred features.

## Performance

| Limitation | Impact | Mitigation |
|------------|--------|------------|
| Dashboard queries run on every page load (with 60s cache) | Slight delay on first load after cache expiry | Redis caching reduces this to < 100ms |
| Report pages load all records into memory | May slow with 10,000+ records | Add pagination to reports in future phase |
| Audit log filter dropdowns load all options | May slow with 1,000+ users/projects | Add search-select component in future phase |
| No database-level pagination on some list views | Large datasets may be slow | All list views use Laravel pagination |

## Features Not Yet Implemented

| Feature | Priority | Notes |
|---------|----------|-------|
| File upload progress indicator | Low | Upload works but no percentage shown |
| Report export button loading state | Low | Export works but button not disabled during generation |
| Email notifications | Medium | Notifications are in-app only; no email dispatch |
| Bulk user import | Low | Users must be created individually |
| Project templates | Low | Projects must be created from scratch |
| Gantt chart / timeline view | Low | Project list is table-based only |
| Real-time WebSocket updates | Low | Notifications require page refresh |
| Two-factor authentication | Medium | Login is password-only |
| API for third-party integration | Low | No REST/GraphQL API exposed |

## Browser Support

| Browser | Support |
|---------|---------|
| Chrome 90+ | ✅ Full support |
| Firefox 90+ | ✅ Full support |
| Safari 14+ | ✅ Full support |
| Edge 90+ | ✅ Full support |
| Internet Explorer | ❌ Not supported |

## File Upload Limits

| Limit | Value |
|-------|-------|
| Profile photo max size | 2 MB |
| Workflow file max size | 10 MB |
| Allowed profile photo types | JPG, JPEG, PNG, WebP |
| Allowed workflow file types | PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG, TXT, CSV, ZIP |

## Scalability Notes

| Component | Current | At 100,000 users |
|-----------|---------|------------------|
| Session storage | Database | Switch to Redis (`SESSION_DRIVER=redis`) |
| Cache | Database | Switch to Redis (`CACHE_STORE=redis`) |
| Queue | Database (sync) | Switch to Redis (`QUEUE_CONNECTION=redis`) |
| File storage | Local disk | Consider S3/CDN for large file volumes |
| Database | Single MySQL | Consider read replicas, partitioning |

## Error Pages

| Error | Behavior |
|-------|----------|
| 404 Not Found | Custom error page with navigation back to dashboard |
| 403 Forbidden | Custom error page with "Access Denied" message |
| 500 Server Error | Custom error page with error ID; no stack trace in production |
| 419 CSRF Expired | Redirect to previous page with error message |
| 422 Validation Failed | Redirect back with field-level error messages |
| 429 Too Many Requests | Retry-After header + error message |

## Security Notes

| Area | Status |
|------|--------|
| CSRF protection | ✅ Enabled on all state-changing routes |
| XSS prevention | ✅ React auto-escapes all output; no dangerouslySetInnerHTML |
| SQL injection | ✅ Laravel query builder uses parameterized queries |
| File upload validation | ✅ Type and size validated server-side |
| Password hashing | ✅ bcrypt with 12 rounds |
| Session fixation | ✅ Session regenerated on login |
| Rate limiting | ✅ Applied to auth, comparison, and notification endpoints |
| Deactivated user blocking | ✅ Middleware blocks access with auto-logout |
