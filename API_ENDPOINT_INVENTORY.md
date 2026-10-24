# Penguin Academy — verified API inventory

All 24 operations match the final Laravel route inventory and unchanged OpenAPI 3.1.1 specification. Every operation returned at least one verified successful response over real HTTP. All 23 protected operations also rejected absent authentication (401) and missing abilities (403). Current policies still apply; see `app/docs/API.md` and `app/docs/openapi.json` for role/ownership rules and request definitions.

| Method | Path | OpenAPI operationId | Required ability | Observed HTTP statuses |
| --- | --- | --- | --- | --- |
| POST | `/api/v1/tokens` | `issueToken` | Credentials | 201, 401, 429 |
| GET | `/api/v1/tokens` | `listTokens` | tokens:read | 200, 401, 403 |
| DELETE | `/api/v1/tokens/current` | `revokeCurrentToken` | tokens:revoke | 204, 401, 403 |
| DELETE | `/api/v1/tokens/{token}` | `revokeOwnedToken` | tokens:revoke | 204, 401, 403 |
| GET | `/api/v1/courses` | `listCourses` | courses:read | 200, 401, 403, 422 |
| POST | `/api/v1/courses` | `createCourse` | courses:write | 201, 401, 403, 422 |
| GET | `/api/v1/courses/{course}` | `showCourse` | courses:read | 200, 401, 403, 404 |
| PATCH | `/api/v1/courses/{course}` | `updateCourse` | courses:write | 200, 401, 403, 422 |
| DELETE | `/api/v1/courses/{course}` | `archiveCourse` | courses:write | 204, 401, 403 |
| PUT | `/api/v1/courses/{course}/publication` | `setCoursePublication` | courses:write | 200, 401, 403 |
| GET | `/api/v1/courses/{course}/lessons` | `listLessons` | lessons:read | 200, 401, 403 |
| POST | `/api/v1/courses/{course}/lessons` | `createLesson` | lessons:write | 201, 401, 403 |
| GET | `/api/v1/courses/{course}/lessons/{lesson}` | `showLesson` | lessons:read | 200, 401, 403, 404 |
| PATCH | `/api/v1/courses/{course}/lessons/{lesson}` | `updateLesson` | lessons:write | 200, 401, 403 |
| DELETE | `/api/v1/courses/{course}/lessons/{lesson}` | `archiveLesson` | lessons:write | 204, 401, 403 |
| PUT | `/api/v1/courses/{course}/lessons/{lesson}/publication` | `setLessonPublication` | lessons:write | 200, 401, 403 |
| PUT | `/api/v1/courses/{course}/lessons/order` | `reorderLessons` | lessons:write | 200, 401, 403, 409 |
| GET | `/api/v1/enrollments` | `listEnrollments` | enrollments:read | 200, 401, 403 |
| POST | `/api/v1/enrollments` | `enrollStudent` | enrollments:write | 200, 201, 401, 403, 422 |
| DELETE | `/api/v1/enrollments/{enrollment}` | `withdrawEnrollment` | enrollments:write | 204, 401, 403 |
| GET | `/api/v1/enrollments/{enrollment}/progress` | `showProgress` | progress:read | 200, 401, 403 |
| PUT | `/api/v1/enrollments/{enrollment}/lessons/{lesson}/completion` | `completeLesson` | progress:write | 200, 401, 403 |
| DELETE | `/api/v1/enrollments/{enrollment}/lessons/{lesson}/completion` | `undoCompletion` | progress:write | 200, 401, 403 |
| GET | `/api/v1/reports/overview` | `academyOverview` | reports:read | 200, 401, 403 |

Evidence: `evidence/m6/api-contract.json`, `evidence/m6/route-contract-parity.json`, and `evidence/m6/final/api-routes.json`. AJV draft-2020-12 validates documented JSON response bodies; 204 responses are verified empty. Credentials/tokens are not recorded.
