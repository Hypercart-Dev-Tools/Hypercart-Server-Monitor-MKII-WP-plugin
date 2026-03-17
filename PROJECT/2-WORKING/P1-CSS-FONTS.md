Refactoring Plan: Unified Benchmark Table CSS
Status: To be Reviewed

Goals: DRY, Maintainability, SSOT, and Remove too many layer

Update: Built new Font Selector UI settings because of back and forth with AI to tweak the fonts

1. Create a Shared CSS File with CSS Variables
Create a new shared.css file that both admin and frontend will import. This file will contain CSS custom properties for colors (status colors like excellent/good/warning/critical), typography (font-family, base sizes), and spacing. Currently, you have duplicated color definitions in admin.css:48-71 and frontend.css:205-253. By centralizing these into :root variables like --hsm-color-excellent: #22c55e, both stylesheets can reference the same values and any change propagates everywhere.

Status: To be Reviewed

2. Unify Table Styling into One Reusable Component
The table styling is the most duplicated code—admin.css:82-112 (31 lines) vs frontend.css:374-487 (113 lines). Merge these into a single .hsm-table class in shared.css that handles headers, cells, hover states, and text alignment. Remove the WordPress-specific widefat dependency and use your own consistent styling. Since you're okay with identical text sizes, pick one font-size (e.g., 14px or 0.875rem) for all table cells across both views.

Status: To be Reviewed

3. Consolidate Score and Status Badge Classes
Currently there are 4 separate implementations of status colors: score display, score badges, table scores, and cron health indicators—each defined twice (admin + frontend). Collapse these into two shared classes: .hsm-status-badge for inline labels and .hsm-score-display for the large score number. Apply color variants via modifier classes like .hsm-status-badge--excellent that reference the CSS variables from step 1.

Status: To be Reviewed

4. Simplify PHP Templates to Use Unified Classes
Update tab-dashboard.php and shortcode-dashboard.php to use the same table class names. Remove the widefat striped WordPress classes from admin tables and replace with .hsm-table. This allows you to delete most of admin.css table-specific styles and trim frontend.css significantly—estimating a reduction from ~1,026 combined lines to ~500.

Example: Unified Table CSS


/* shared.css - replaces duplicate code in both files */
:root {
  --hsm-text: #1e293b;
  --hsm-border: #e2e8f0;
  --hsm-excellent: #22c55e;
  --hsm-warning: #f59e0b;
  --hsm-critical: #ef4444;
}

.hsm-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  color: var(--hsm-text);
}

.hsm-table th,
.hsm-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--hsm-border);
  text-align: left;
}

.hsm-table tbody tr:hover {
  background: #f8fafc;
}

---

## Implementation Notes (Completed 2026-01-31)

### Files Created
- `assets/shared.css` - New unified stylesheet with CSS variables, table styles, status colors

### Files Modified
- `assets/admin.css` - Removed ~60 lines of duplicated table/status styles, added LLM guidance
- `assets/frontend.css` - Removed ~100 lines of duplicated table/status styles, added LLM guidance
- `src/Admin/AdminController.php` - Added shared.css enqueue before admin.css
- `src/Plugin.php` - Added shared.css enqueue before frontend.css

### Standards Applied
- **Font size**: 14px for all table content (unified)
- **Text color**: #000 (pure black) for maximum legibility
- **CSS Variables**: Centralized in shared.css for easy theming

### LLM Guidance Added
Both admin.css and frontend.css now contain header comments instructing future LLMs:
- DO NOT increase CSS complexity without explicit user approval
- DO NOT add new CSS classes unless specifically requested
- DO NOT duplicate styles - use shared.css for common components
- DO NOT change font sizes - 14px and #000 text are standard