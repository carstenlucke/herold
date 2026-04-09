# GitHub Issues as Sole Ticket Store -- Variant Comparison

## Context

Herold dispatches voice-recorded tasks to local AI agents (Claude Code, OpenCode).
The original design stored tickets locally (SQLite) and maintained a parallel agent
memory system, with GitHub Issues as one delivery channel. This created three
responsibilities for the application: voice processing, ticket management, and
agent memory.

The question: should Herold manage tickets and agent memory internally, or should
it delegate ticket storage entirely to GitHub and defer agent memory to a later
stage?

Key constraints:
- Herold is a single-user app, primarily used on mobile for quick voice input
- Local AI agents (Claude Code, OpenCode) already have native GitHub support via `gh` CLI
- The app runs on shared hosting with limited resources (no shell access, FTP deployment)
- Agent memory has fundamentally different access patterns than ticketing (high-frequency reads, structured queries, full-text search)

---

## Option 1: Local ticket management + agent memory in SQLite

**Concept:** Herold stores tickets and agent memories in local SQLite tables. Agents
interact with Herold's API (Sanctum token auth) for both tickets and memory.
GitHub is not used.

**Pros:**
- Single data source -- everything in SQLite, consistent architecture
- Full control over data schema, queries, and workflow states
- No external dependency, no rate limits, offline-capable
- Unified agent API (one endpoint for tickets and memory)

**Cons:**
- Herold must implement full ticket CRUD, status workflow, audit trail, and UI
- Herold must implement memory CRUD, full-text search, scope/category filtering
- Agents cannot create tickets without Herold (no direct GitHub access, no web UI fallback)
- Significant development effort for features that existing tools (GitHub) already provide
- Herold becomes a platform rather than a focused tool

**Effort:** High -- ticket management, memory system, agent API, and corresponding UI.

---

## Option 2: GitHub Issues as ticket store + local agent memory

**Concept:** Herold pushes processed voice notes to GitHub Issues (one-way). Ticket
management happens in GitHub. Agent memory remains in Herold's SQLite database
with a dedicated API.

**Pros:**
- Tickets benefit from GitHub's existing infrastructure (comments, labels, notifications, web UI)
- Agents access tickets natively via `gh` CLI -- no custom API integration needed
- Herold still provides structured memory storage with full-text search

**Cons:**
- Two systems for agents: Herold API (memory) + GitHub (tickets)
- Agent memory is a speculative feature -- unclear if agents will actually use it
- Memory system adds development effort for uncertain value
- Architectural inconsistency: some data local, some external

**Effort:** Medium -- GitHub integration for tickets, plus memory system.

---

## Option 3: GitHub Issues as ticket store, no agent memory (defer to later)

**Concept:** Herold is a focused voice-to-GitHub-Issue dispatcher. It records audio,
transcribes, processes via LLM, and pushes the result as a GitHub Issue. No local
ticket management, no agent memory. Agents interact exclusively with GitHub.

Tickets can also be created through other channels: GitHub web UI, `gh` CLI,
other agents, or automation tools. Herold is one input channel among many.

**Pros:**
- Herold has a single, clear responsibility: voice → GitHub Issue
- Minimal application complexity -- no ticket CRUD, no memory system, no agent API
- Agents use GitHub natively (`gh issue list/view/comment`) -- zero onboarding
- GitHub provides audit trail (issue events, comments) and agent communication (comments) for free
- Tickets can be created from any source, not just Herold
- Significantly less code to build and maintain
- Agent memory can be added later if a real need emerges (YAGNI)

**Cons:**
- External dependency on GitHub API (rate limits: 5,000/h for authenticated requests)
- No offline ticket creation (voice notes are stored locally, but GitHub push requires connectivity)
- Labels as status system is limited (no state machine validation)
- No structured agent memory -- agents must manage their own context
- If agent memory is needed later, it must be designed and built then

**Effort:** Low -- voice processing pipeline + one-way GitHub push.

---

## Decision: Option 3 -- GitHub Issues as sole ticket store, agent memory deferred

**Rationale:**

1. **Clear responsibility.** Herold does one thing well: capture voice input on the go
   and dispatch it as a structured GitHub Issue. It is an input channel, not a project
   management platform.

2. **Native agent support.** Claude Code and OpenCode understand GitHub Issues
   out of the box. `gh issue list`, `gh issue view`, `gh issue comment` work
   without any custom integration. No Sanctum tokens, no API documentation,
   no onboarding.

3. **GitHub as communication platform.** Agents can exchange information via issue
   comments -- a transparent, searchable protocol that requires zero application
   code. The audit trail (who did what, when) comes for free via GitHub's event system.

4. **Multiple input channels.** With GitHub as the ticket store, tickets can originate
   from Herold (voice), the GitHub web UI (manual), `gh` CLI (terminal), or any
   other tool. Herold does not need to be the single point of entry.

5. **YAGNI for agent memory.** Agent memory was a speculative feature. The actual
   need and access patterns are unclear. Building it now would add complexity for
   uncertain value. If a real need emerges, it can be added as a focused feature
   with concrete requirements.

6. **Less code, fewer failure modes.** Removing ticket management and memory
   eliminates: `TicketController`, `AgentTicketController`, `MemoryController`,
   `MemoryService`, `Memory` model, `memories` migration, `MemoryScope`/`MemoryCategory`
   enums, and all corresponding API routes, tests, and UI components.

**Rejected alternatives:**
- **Option 1 (all local):** Too much scope for a single-user voice dispatcher.
  Rebuilds functionality that GitHub already provides, and isolates Herold as the
  only way to create tickets.
- **Option 2 (hybrid):** Reduces scope compared to Option 1, but retains the
  speculative memory system. Two systems for agents (Herold API + GitHub) adds
  integration complexity without clear benefit.

**Consequences:**
- Herold stores `voice_notes` in SQLite (audio path, transcript, processed result, GitHub reference)
- No `memories` table, no `Memory` model, no memory-related API routes
- No `TicketController` or `AgentTicketController` -- no local ticket management
- No agent API (`api.php` routes removed entirely, Sanctum token auth removed)
- `GitHubService` creates issues (one-way push) -- no read-back, no sync
- `VoiceNote` keeps `github_issue_number` and `github_issue_url` as reference after push
- Settings page simplified: no token management section
- Agent communication happens in GitHub issue comments, not in Herold
