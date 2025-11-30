# Comments System Refactor - Context Transfer Summary

**Branch:** `comments`  
**Full Plan:** `docs/comments_refactor_plan.md` (Czech, 600+ lines)

---

## 🎯 Goal

Modernize comments/issues system from custom Renderer classes to CakePHP 2 Elements, with proper CRUD operations and global issues management.

---

## ✅ Already Complete

- **Database:** Migration `20251127021907_convert_comments.php` created tables:
  - `tsumego_issue_status` (opened=1, closed=2, reviewed=3, deleted=4)
  - `tsumego_issue` (id, tsumego_id, user_id, status_id, created)
  - `tsumego_comment` (id, tsumego_id, tsumego_issue_id, message, user_id, created, position, deleted)
  
- **Models exist:** `TsumegoIssue.php`, `TsumegoComment.php`

- **Controller exists:** `TsumegoCommentController.php` (singular - needs rename to plural)

- **Elements work:** Verified CakePHP finds them in `src/View/Elements/`

---

## 🚧 Phase 0 - Prep (Current)

| Task | Status |
|------|--------|
| Rename `TsumegoCommentController` → `TsumegoCommentsController` | TODO |
| Create `TsumegoIssuesController` | TODO |
| Delete/refactor old Renderers in `src/Utility/` | TODO |

**Old Renderers to remove:**
- `TsumegoCommentsRenderer.php`
- `TsumegoIssuesRenderer.php`  
- `TsumegoCommentsSectionRenderer.php`

---

## 📁 Files to Create

```
src/Controller/TsumegoCommentsController.php  (rename from singular)
src/Controller/TsumegoIssuesController.php    (NEW)
src/View/Elements/Tsumego/
    comment.ctp
    issue.ctp
    comments_section.ctp
    comment_form.ctp
src/View/TsumegoIssues/index.ctp              (global issues page)
```

---

## 📦 API Endpoints

**TsumegoCommentsController:**
```
POST /tsumego-comments/add/:tsumegoId      - add comment
POST /tsumego-comments/delete/:commentId   - delete (owner/admin)
```

**TsumegoIssuesController:**
```
GET  /tsumego-issues                       - list all issues
POST /tsumego-issues/create/:tsumegoId     - create issue + first comment
POST /tsumego-issues/close/:issueId        - close issue
POST /tsumego-issues/reopen/:issueId       - reopen (admin only)
POST /tsumego-issues/move-comment/:commentId - move comment to issue (admin)
```

---

## 🔑 Authorization Rules

| Action | Who |
|--------|-----|
| Add comment | Logged-in user |
| Create issue | Logged-in user |
| Close issue | Admin OR issue author |
| Reopen issue | Admin only |
| Move comment to issue | Admin only |
| Delete comment | Admin OR comment author |

---

## 🎨 UI Design

**Tabs:** ALL | COMMENTS | ISSUES (with open count)

**Visual distinction:**
- **Issues:** Boxed with colored badge (🔴 OPENED / ✅ CLOSED)
- **Comments:** Plain, no box

**Sorting:** Chronological by created date

---

## 📋 Implementation Phases

| Phase | Description | Status |
|-------|-------------|--------|
| 0 | Prep: controllers, delete renderers | 🔄 IN PROGRESS |
| 1 | Display: Elements + CSS | TODO |
| 2 | CRUD: comment add/delete | TODO |
| 3 | Issues: create/close/reopen/move | TODO |
| 4 | Global: issues index page | TODO |

---

## 💡 Key Technical Notes

1. **No Helper needed** - use existing `TsumegosController::commentCoordinates()` static method
2. **Data loading** moves from Renderers to `Play.php` component
3. **Old `Comment` model** stays for backward compatibility (different table)
4. **Elements path:** `src/View/Elements/` (verified working)
5. **Routes:** Add to `config/routes.php`

---

## 🔗 Key Files

- **Plan:** `docs/comments_refactor_plan.md`
- **Migration:** `db/migrations/20251127021907_convert_comments.php`
- **Current rendering:** `src/View/Tsumegos/play.ctp` line ~597 calls `TsumegoCommentsRenderer`
- **Play component:** `src/Controller/Component/Play.php`
