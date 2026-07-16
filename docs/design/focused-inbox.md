# Design note: The Focused Stream (inbox decluttering)

Status: **concept / thinking** — not built. Captured 2026-07-16.

## Where this comes from

Spark's Smart Inbox does something genuinely good: Pinned / Notifications / Newsletters live
*inside* the inbox stream, but bulk mail is folded into compact, collapsible clusters so it
doesn't push your real mail down. Signal stays up, noise folds in — without leaving the view.

We already have the ingredients (per-message `group_type` = people/newsletter/notification,
plus Pinned) but exposed them the conventional way: as **separate sidebar views** you click
into. Good for "show me only newsletters", but it doesn't declutter the daily inbox itself.

Steve's angle (the better one): **don't copy the bundling — invert it.** Anything that is
noise *and* already has a home in the sidebar can leave the inbox stream entirely. The sidebar
groups become the permanent by-type reference; the stream becomes the working surface.

## The core idea: reading is triage

The inbox stream shows only mail that still wants something from you. The novel part — the bit
that makes it *ours* and not a Spark clone — is the **rule for when something leaves**:

> A newsletter/notification stays in the stream **while it is unread**. The moment you've seen
> it (here, on the phone, anywhere — flags already sync), it retreats to its sidebar group.

So the stream self-cleans through normal use. No bundles to expand, no folder to visit in the
daily flow. Spark asks you to *manage* clusters; Barua removes noise *as a side effect of
reading it*. Subtractive, not organizational.

## Draft rules

- **People**: always in the stream. Conversations may need an ongoing reply; they don't decay.
- **Unclassified (`other`)**: treated like People (in the stream) until proven noise.
- **Newsletters / Notifications**: in the stream only while **unread**. Once read → gone from
  the stream, still one click away in the sidebar group.
- **Pinned**: floats to the top of the stream regardless of type. Pin = "keep this in front of
  me", so pinning is the manual override that beats decay.

Morning flow: open Barua → stream = your people + today's fresh bulk. Read the two newsletters
that matter, they fade; your real mail stays. The sidebar groups hold "everything of this
type, ever".

## Two declutter gestures, one safety net (Steve, 2026-07-16)

The read-gate alone has a hole: newsletters you *never* read would linger in the stream (or
only leave via the freshness fallback). Archive fills it as the **active** gesture — and the
smart groups are what make archiving feel safe rather than like deletion.

- **Passive**: read → leaves the stream (mail you skim).
- **Active**: archive → leaves the stream (the newsletter you'll never open).
- **Both** keep the mail visible in its **smart sidebar group** — Newsletters / Notifications
  show `folder_role IN ('inbox','archive')`, so archiving hides a mail from the daily stream
  but never from its category. The group is the memory; nothing is lost.

This is the "like Spark but different" the concept was reaching for. Spark archives everything
and offers a **global** "show archived" toggle. Barua doesn't auto-archive; archive stays a
deliberate act, and the "still see it" surface is **the smart group it belongs to**, not a
global switch. Archived-but-categorised mail resurfaces exactly where it's relevant.

Implementation-wise this is small and mostly already in place: `group_type` is classified for
archived mail too (sync covers the Archive folder), Pinned already includes archived — so
extending `groupMessages()` to `folder_role IN ('inbox','archive')` is the core change. Nice
touches to consider: a subtle "Archived" divider or dimming within the group so inbox vs
archived is legible; a per-group bulk gesture ("archive all read", "clear this group").

Consequence worth noting: with archive as the escape hatch, the 24–48h freshness fallback may
be unnecessary — the user has a one-click way to dismiss unread noise, so the stream doesn't
strictly need to time-decay it. Simpler model: **stream = People + Pinned + UNREAD, un-archived
newsletters/notifications.** Reading or archiving are the only two ways things leave.

## Refinements to consider (not decided)

- **Freshness fallback**: leave the stream when read **OR** older than ~24–48h, whichever comes
  first — so unread-but-ignored bulk doesn't pile up forever in the stream either.
- **Newsletters vs Notifications may want different behaviour**: a notification (bank alert,
  "order shipped", password reset) can matter more than a newsletter. Maybe notifications get a
  longer/again-on-new window; newsletters decay fast. Keep it tunable.
- **Optional digest peek**: a single slim, dismissible line where the pulled-out mail would
  have sat chronologically — "+9 read newsletters today" — for people who still want the
  glance. Off by default (the whole point is a clean stream).
- **Per-group opt-out**: "keep Newsletters in the stream" toggle for someone who reads
  newsletters as they arrive and doesn't want them to vanish.
- **Read-elsewhere is a feature, not a bug**: because \Seen syncs, reading on the phone also
  declutters Barua's stream. Triage once, anywhere.
- Later: fold **Snooze** into the same model (snoozed = temporarily out of the stream, returns
  at a time).

## Open questions

- Is pure read-state enough, or is the freshness fallback needed from day one?
- Do we ever *remove* People from the stream (e.g. a resolved thread), or is that Archive's job?
  (Leaning: Archive stays the manual "done" action; the stream never auto-drops people.)
- How does this interact with the per-account scope filter? (Likely: same rule applies within
  whatever scope is active.)

## Why not just filter the inbox to People?

Because that hides *new* bulk too — you'd miss the newsletter you actually care about until you
remember to check its folder. The unread-gate keeps new noise visible exactly once, then lets
it go. That single distinction is the whole design.
