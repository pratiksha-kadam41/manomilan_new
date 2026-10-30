import { useEffect, useId, useRef } from "react";
import {
  AlertCircle, CheckCircle2, ChevronLeft, ChevronRight, Info, Loader2,
  RefreshCw, X,
} from "lucide-react";
import { clampPercent, cx, initials, statusClass, statusLabel } from "../utils";

export function PageHeader({ eyebrow, title, subtitle, actions, children }) {
  return (
    <section className="cnd-page-head">
      <div className="cnd-page-head-copy">
        {eyebrow ? <span className="cnd-eyebrow">{eyebrow}</span> : null}
        <h1>{title}</h1>
        {subtitle ? <p>{subtitle}</p> : null}
        {children}
      </div>
      {actions ? <div className="cnd-page-head-actions">{actions}</div> : null}
    </section>
  );
}

export function SectionCard({ title, subtitle, action, children, className = "", icon: Icon }) {
  return (
    <section className={cx("cnd-card", className)}>
      {title || action ? (
        <header className="cnd-card-head">
          <div className="cnd-card-head-copy">
            {Icon ? (
              <span className="cnd-card-icon"><Icon size={18} /></span>
            ) : null}
            <div>
              <h2>{title}</h2>
              {subtitle ? <p>{subtitle}</p> : null}
            </div>
          </div>
          {action ? <div className="cnd-card-head-action">{action}</div> : null}
        </header>
      ) : null}
      {children}
    </section>
  );
}

export function Avatar({ name = "", src = "", size = "md", className = "" }) {
  return (
    <span className={cx("cnd-avatar", `cnd-avatar-${size}`, className)}>
      {src ? <img src={src} alt={name ? `${name} profile` : ""} loading="lazy" decoding="async" /> : initials(name)}
    </span>
  );
}

export function StatusBadge({ status, children }) {
  return (
    <span className={cx("cnd-status", statusClass(status))}>
      {children || statusLabel(status)}
    </span>
  );
}

export function ProgressRing({ value = 0, size = 96, stroke = 8, label, tone = "pink" }) {
  const percent = clampPercent(value);
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (percent / 100) * circumference;

  return (
    <div className={cx("cnd-ring", `cnd-ring-${tone}`)} style={{ width: size, height: size }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} role="img" aria-label={`${percent}%`}>
        <circle className="cnd-ring-track" cx={size / 2} cy={size / 2} r={radius} strokeWidth={stroke} fill="none" />
        <circle
          className="cnd-ring-value"
          cx={size / 2}
          cy={size / 2}
          r={radius}
          strokeWidth={stroke}
          fill="none"
          strokeDasharray={circumference}
          strokeDashoffset={offset}
          strokeLinecap="round"
          transform={`rotate(-90 ${size / 2} ${size / 2})`}
        />
      </svg>
      <span className="cnd-ring-text">
        <strong>{percent}%</strong>
        {label ? <small>{label}</small> : null}
      </span>
    </div>
  );
}

export function ProgressBar({ value = 0, tone = "pink", label }) {
  const percent = clampPercent(value);
  return (
    <div className={cx("cnd-progress", `cnd-progress-${tone}`)}>
      {label ? (
        <div className="cnd-progress-top">
          <span>{label}</span>
          <strong>{percent}%</strong>
        </div>
      ) : null}
      <div className="cnd-progress-track">
        <span style={{ width: `${percent}%` }} />
      </div>
    </div>
  );
}

export function EmptyState({ icon: Icon, title, message, action, tone = "pink" }) {
  return (
    <div className={cx("cnd-empty-state", `tone-${tone}`)}>
      {Icon ? <span className="cnd-empty-icon"><Icon size={26} /></span> : null}
      <h3>{title}</h3>
      {message ? <p>{message}</p> : null}
      {action ? <div className="cnd-empty-action">{action}</div> : null}
    </div>
  );
}

export function ErrorState({ message, onRetry }) {
  return (
    <div className="cnd-error-state" role="alert">
      <span className="cnd-error-icon"><AlertCircle size={22} /></span>
      <div>
        <strong>Something went wrong</strong>
        <p>{message || "We could not load this section right now."}</p>
      </div>
      {onRetry ? (
        <button type="button" className="cnd-btn cnd-btn-ghost" onClick={onRetry}>
          <RefreshCw size={16} /> Retry
        </button>
      ) : null}
    </div>
  );
}

export function Spinner({ size = 18, label }) {
  return (
    <span className="cnd-spinner" aria-live="polite">
      <Loader2 size={size} className="cnd-spin" />
      {label ? <span>{label}</span> : null}
    </span>
  );
}

export function Skeleton({ className = "", lines = 1 }) {
  if (lines > 1) {
    return (
      <div className={cx("cnd-skeleton-stack", className)}>
        {Array.from({ length: lines }).map((_, index) => (
          <span key={index} className="cnd-skeleton" style={{ width: `${92 - index * 12}%` }} />
        ))}
      </div>
    );
  }
  return <span className={cx("cnd-skeleton", className)} />;
}

export function DashboardSkeleton() {
  return (
    <div className="cnd-dash-skeleton">
      <div className="cnd-skeleton cnd-skeleton-hero" />
      <div className="cnd-skeleton-grid">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="cnd-skeleton cnd-skeleton-stat" />
        ))}
      </div>
      <div className="cnd-skeleton-grid two">
        <div className="cnd-skeleton cnd-skeleton-panel" />
        <div className="cnd-skeleton cnd-skeleton-panel" />
      </div>
    </div>
  );
}

export function CardGridSkeleton({ count = 6 }) {
  return (
    <div className="cnd-match-grid">
      {Array.from({ length: count }).map((_, index) => (
        <div key={index} className="cnd-skeleton cnd-skeleton-card" />
      ))}
    </div>
  );
}

export function Pagination({ pagination, onPage, busy = false }) {
  if (!pagination || pagination.last_page <= 1) return null;
  const { current_page: page, last_page: last, total, from, to } = pagination;
  const pages = [];
  const start = Math.max(1, page - 1);
  const end = Math.min(last, page + 1);
  for (let index = start; index <= end; index += 1) pages.push(index);

  return (
    <nav className="cnd-pagination" aria-label="Pagination">
      <span className="cnd-pagination-meta">
        Showing {from}-{to} of {total}
      </span>
      <div className="cnd-pagination-controls">
        <button
          type="button"
          className="cnd-page-btn"
          onClick={() => onPage(page - 1)}
          disabled={page <= 1 || busy}
          aria-label="Previous page"
        >
          <ChevronLeft size={17} />
        </button>
        {start > 1 ? (
          <>
            <button type="button" className="cnd-page-btn" onClick={() => onPage(1)} disabled={busy}>1</button>
            {start > 2 ? <span className="cnd-page-ellipsis">...</span> : null}
          </>
        ) : null}
        {pages.map((value) => (
          <button
            key={value}
            type="button"
            className={cx("cnd-page-btn", value === page && "active")}
            onClick={() => onPage(value)}
            disabled={busy}
            aria-current={value === page ? "page" : undefined}
          >
            {value}
          </button>
        ))}
        {end < last ? (
          <>
            {end < last - 1 ? <span className="cnd-page-ellipsis">...</span> : null}
            <button type="button" className="cnd-page-btn" onClick={() => onPage(last)} disabled={busy}>{last}</button>
          </>
        ) : null}
        <button
          type="button"
          className="cnd-page-btn"
          onClick={() => onPage(page + 1)}
          disabled={page >= last || busy}
          aria-label="Next page"
        >
          <ChevronRight size={17} />
        </button>
      </div>
    </nav>
  );
}

export function Modal({ open, onClose, title, eyebrow, children, footer, size = "md" }) {
  const titleId = useId();
  const panelRef = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    const onKey = (event) => {
      if (event.key === "Escape") onClose?.();
    };
    document.addEventListener("keydown", onKey);
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const timer = window.setTimeout(() => panelRef.current?.focus(), 30);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = previous;
      window.clearTimeout(timer);
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="cnd-modal-layer" role="dialog" aria-modal="true" aria-labelledby={titleId}>
      <button type="button" className="cnd-modal-backdrop" aria-label="Close dialog" onClick={onClose} />
      <div className={cx("cnd-modal", `cnd-modal-${size}`)} ref={panelRef} tabIndex={-1}>
        <header className="cnd-modal-head">
          <div>
            {eyebrow ? <span className="cnd-eyebrow">{eyebrow}</span> : null}
            <h2 id={titleId}>{title}</h2>
          </div>
          <button type="button" className="cnd-icon-btn" onClick={onClose} aria-label="Close">
            <X size={20} />
          </button>
        </header>
        <div className="cnd-modal-body">{children}</div>
        {footer ? <footer className="cnd-modal-foot">{footer}</footer> : null}
      </div>
    </div>
  );
}

export function ConfirmDialog({
  open, title = "Are you sure?", message, confirmLabel = "Confirm",
  cancelLabel = "Cancel", tone = "danger", busy = false, onConfirm, onCancel,
}) {
  return (
    <Modal
      open={open}
      onClose={busy ? undefined : onCancel}
      title={title}
      size="sm"
      footer={(
        <div className="cnd-confirm-actions">
          <button type="button" className="cnd-btn cnd-btn-ghost" onClick={onCancel} disabled={busy}>
            {cancelLabel}
          </button>
          <button
            type="button"
            className={cx("cnd-btn", tone === "danger" ? "cnd-btn-danger" : "cnd-btn-primary")}
            onClick={onConfirm}
            disabled={busy}
          >
            {busy ? <Spinner size={16} /> : null}
            {confirmLabel}
          </button>
        </div>
      )}
    >
      <p className="cnd-confirm-text">{message}</p>
    </Modal>
  );
}

export function Toasts({ items = [], onDismiss }) {
  if (!items.length) return null;
  return (
    <div className="cnd-toasts" aria-live="polite">
      {items.map((toast) => {
        const Icon = toast.tone === "error" ? AlertCircle : toast.tone === "info" ? Info : CheckCircle2;
        return (
          <div key={toast.id} className={cx("cnd-toast", toast.tone)}>
            <Icon size={18} />
            <span>{toast.message}</span>
            <button type="button" onClick={() => onDismiss(toast.id)} aria-label="Dismiss">
              <X size={15} />
            </button>
          </div>
        );
      })}
    </div>
  );
}

export function FormField({
  label, name, children, hint, error, required, className = "", htmlFor,
}) {
  return (
    <div className={cx("cnd-field", className)}>
      {label ? (
        <label htmlFor={htmlFor || name}>
          {label}
          {required ? <span aria-hidden="true">*</span> : null}
        </label>
      ) : null}
      {children}
      {hint && !error ? <small className="cnd-field-hint">{hint}</small> : null}
      {error ? <small className="cnd-field-error">{error}</small> : null}
    </div>
  );
}

export function InlineNote({ tone = "info", children }) {
  const Icon = tone === "error" ? AlertCircle : tone === "success" ? CheckCircle2 : Info;
  return (
    <div className={cx("cnd-inline-note", tone)}>
      <Icon size={16} />
      <span>{children}</span>
    </div>
  );
}
