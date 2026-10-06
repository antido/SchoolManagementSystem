# Task 25 — File Attachments

## Objective
Create secure file upload/download support for assignments, submissions, profile images, and awards.

## Storage
Use Laravel Filesystem.

Suggested directories:
```text
assignments/
submissions/
avatars/
awards/
forum/
```

## Requirements
- Validate file type.
- Validate file size.
- Generate safe filenames.
- Avoid trusting client-provided paths.
- Authorize downloads.
- Support deletion/replacement.
- Store metadata where appropriate.

## Acceptance Criteria
- Users cannot download files they are not authorized to access.
- Invalid uploads are rejected.
- Files are stored outside direct public access when appropriate.
