// CSS barrel for the main app theme bundle.
// Layer order is set once in layers.css — import order between layers doesn't matter.
import './layers.css';
import './tokens.css';
import './base.css';
import './components.css';
// React feature components (comments/issues/dnd) - same components layer as
// components.css so React UI keeps identical priority.
import './react/comments.css';
import './react/issues.css';
import './react/dnd.css';
// Page layer: page-specific rules split by feature (shrinks to zero over time).
import './page/fonts.css';
import './page/site.css';
import './page/play.css';
import './page/home.css';
import './page/sets.css';
import './page/profile.css';
import './page/achievements.css';
import './page/timemode.css';
import './page/admin.css';
import './page/about.css';
import './page/auth.css';
import './page/misc.css';
import './home-themes.css';
import './profile.css';
import './sets.css';
// Third-party chart theme (unlayered; only styles apexcharts-* classes).
import './apexcharts-theme.css';
