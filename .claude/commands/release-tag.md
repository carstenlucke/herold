---
description: "Tags a release version, ensures everything is committed and pushed, and pushes the tag to GitHub to trigger the release workflow."
argument-hint: "[version]"
---

You are a release engineer for the TimeLedger Electron app.

Your task is to **tag and publish a release** so the GitHub Actions release workflow is triggered.

The user provided the following version argument (may be empty): $ARGUMENTS

## Workflow

Follow these steps **in order**. Do not skip steps.

### Step 1 — Version number in package.json

- Read the current `version` field from `package.json`.
- **If the user provided a version number as argument**, compare it to the value in `package.json`:
  - If they match, continue.
  - If they differ, update the `version` field in `package.json` to the provided version and confirm the change.
- **If no version argument was provided**, ask the user:
  - Whether the current version is correct for this release, **or**
  - Whether it should be updated (and if so, to which value).

Store the resolved version number for all subsequent steps.

### Step 2 — Release letter

Ask the user:

> Should I generate a release letter now (via `/release-letter`), or has one already been created?

- If the user wants generation: invoke the `/release-letter` command and wait for it to complete.
- If the user says it already exists: continue.

### Step 3 — Review release letter

After the release letter exists, ask the user:

> Do you want to review or edit the release letter before continuing?

- If **yes**: pause and tell the user to edit and save the file. Wait for the user to confirm they are done.
- If **no**: continue.

### Step 4 — Git status check

Run `git status` and `git log origin/main..HEAD` to determine:

1. Whether there are **uncommitted changes** (staged or unstaged).
2. Whether there are **unpushed commits**.

**If the working tree is clean and up-to-date with the remote**: continue to Step 5.

**If there are uncommitted changes or unpushed commits**:

- Show the user the current status.
- Offer to create a conventional commit message, commit, and push. For example:
  ```
  chore(release): prepare v<version>
  ```
- If the user declines the commit/push, ask whether to **continue anyway** or **abort** the release process.

### Step 5 — Create and push the version tag

Create and push the tag:

```bash
git tag v<version>
git push origin v<version>
```

Where `<version>` is the version resolved in Step 1 (e.g. `1.7.0` produces tag `v1.7.0`).

### Step 6 — Done

Confirm that the tag has been pushed and inform the user that the GitHub Actions release workflow should now be triggered.

## Rules

- Always use the tag format `v<major>.<minor>.<patch>`.
- Never force-push tags.
- If any step fails, stop and report the error clearly. Do not continue.
- Keep console output concise — no unnecessary logs or summaries.