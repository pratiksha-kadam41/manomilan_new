import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  Bell, CheckCheck, CheckCircle2, Heart, Inbox, Loader2, Settings2, Trash2,
  UserRound, Sparkles,
} from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import MatchDetailModal from "../components/MatchDetailModal";
import {
  ConfirmDialog, EmptyState, ErrorState, PageHeader, Pagination, Skeleton,
  Toasts,
} from "../components/Ui";
import { useToasts } from "../useToasts";
import { getCachedHomeData } from "../../auth";
import {
  deleteNotification, getCandidateProfile, getNotifications,
  markAllNotificationsRead, markNotificationRead,
} from "../api";
import { dispatchNotificationsChanged, groupNotifications, timeAgo } from "../utils";
import "./Notifications.css";

const FILTERS = [
  ["all", "Any status"],
  ["unread", "Unread"],
  ["read", "Read"],
];

const CATEGORIES = [
  ["all", "All"],
  ["interests", "Interests"],
  ["matches", "Matches"],
  ["system", "System"],
];

const iconFor = (type = "") => {
  const normalizedType = String(type || "").toLowerCase();
  if (normalizedType.includes("interest") || normalizedType.includes("connect")) return Heart;
  if (normalizedType.includes("match")) return Sparkles;
  if (normalizedType.includes("document") || normalizedType.includes("verified")) return CheckCircle2;
  if (normalizedType.includes("preference")) return Settings2;
  if (normalizedType.includes("profile")) return UserRound;
  return Inbox;
};

export default function Notifications() {
  const cached = useMemo(() => getCachedHomeData(), []);
  const [candidateInfo, setCandidateInfo] = useState({
    name: cached?.profile?.full_name || cached?.user?.name || cached?.user?.username,
    id: cached?.profile?.registration_number || cached?.profile?.profile_id || cached?.user?.username,
    photo: cached?.profile?.photo_url || "",
  });
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [unreadCount, setUnreadCount] = useState(0);
  const [category, setCategory] = useState("all");
  const [filter, setFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [busyId, setBusyId] = useState(null);
  const [markingAll, setMarkingAll] = useState(false);
  const [removeTarget, setRemoveTarget] = useState(null);
  const [removing, setRemoving] = useState(false);
  const [selected, setSelected] = useState(null);
  const loadRequestId = useRef(0);
  const { toasts, push, dismiss } = useToasts();

  const load = useCallback(async () => {
    const requestId = ++loadRequestId.current;
    setLoading(true);
    setError("");
    try {
      const result = await getNotifications({
        category,
        filter,
        page,
        per_page: 12,
      });
      if (requestId !== loadRequestId.current) return;
      setItems(result?.notifications || []);
      setPagination(result?.pagination || null);
      setUnreadCount(Number(result?.unread_count) || 0);
    } catch (loadError) {
      if (requestId !== loadRequestId.current) return;
      setError(loadError.message || "Could not load notifications.");
    } finally {
      if (requestId === loadRequestId.current) setLoading(false);
    }
  }, [category, filter, page]);

  useEffect(() => {
    getCandidateProfile()
      .then((result) => {
        if (result?.profile) {
          setCandidateInfo({
            name: result.profile.full_name || result.user?.name,
            id: result.profile.registration_number || result.profile.profile_id || result.user?.username,
            photo: result.profile.photo_url || "",
          });
        }
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    Promise.resolve().then(load);
    return () => {
      loadRequestId.current += 1;
    };
  }, [load]);

  const changeCategory = (value) => {
    setCategory(value);
    setPage(1);
  };

  const changeFilter = (value) => {
    setFilter(value);
    setPage(1);
  };

  const markRead = async (item) => {
    if (item.read || busyId === item.id) return;
    setBusyId(item.id);
    try {
      const result = await markNotificationRead(item.id);
      setUnreadCount(Number(result?.unread_count) || 0);
      if (filter === "unread") {
        await load();
      } else {
        setItems((current) => current.map((entry) => (
          entry.id === item.id ? { ...entry, read: true, read_at: new Date().toISOString() } : entry
        )));
      }
      dispatchNotificationsChanged();
    } catch (readError) {
      push(readError.message || "Could not mark this notification as read.", "error");
    } finally {
      setBusyId(null);
    }
  };

  const openNotification = (item) => {
    markRead(item);
    const profileId = Number(item.data?.profile_id || 0);
    if (profileId) setSelected({ id: profileId });
  };

  const markAll = async () => {
    if (!unreadCount) return;
    setMarkingAll(true);
    try {
      await markAllNotificationsRead();
      setItems((current) => current.map((entry) => ({ ...entry, read: true })));
      setUnreadCount(0);
      dispatchNotificationsChanged();
      push("All notifications marked as read.");
      if (filter === "unread") await load();
    } catch (markError) {
      push(markError.message || "Could not mark all notifications as read.", "error");
    } finally {
      setMarkingAll(false);
    }
  };

  const removeNotification = async () => {
    if (!removeTarget) return;
    setRemoving(true);
    try {
      const result = await deleteNotification(removeTarget.id);
      setItems((current) => current.filter((entry) => entry.id !== removeTarget.id));
      setUnreadCount(Number(result?.unread_count) || 0);
      dispatchNotificationsChanged();
      setRemoveTarget(null);
      push("Notification removed.");
    } catch (removeError) {
      push(removeError.message || "Could not remove this notification.", "error");
    } finally {
      setRemoving(false);
    }
  };

  const groups = groupNotifications(items);

  return (
    <CandidateLayout currentPath="/candidate/notifications" candidateInfo={candidateInfo}>
      <div className="notifications-page">
        <Toasts items={toasts} onDismiss={dismiss} />
        <PageHeader
          eyebrow="Your updates"
          title="Notifications"
          subtitle="Interests, match activity and account updates—all in one place."
          actions={(
            <button type="button" className="cnd-btn cnd-btn-primary" onClick={markAll} disabled={markingAll || !unreadCount}>
              {markingAll ? <Loader2 size={16} className="cnd-spin" /> : <CheckCheck size={16} />}
              Mark all as read
            </button>
          )}
        />

      <section className="cnd-card cnd-notifications-card">
        <header className="cnd-notifications-head">
          <div className="notifications-category-tabs" role="tablist" aria-label="Notification category">
            {CATEGORIES.map(([value, label]) => (
              <button
                key={value}
                type="button"
                role="tab"
                aria-selected={category === value}
                className={category === value ? "active" : ""}
                onClick={() => changeCategory(value)}
              >
                {label}
              </button>
            ))}
          </div>
          <div className="cnd-segmented" role="tablist" aria-label="Filter notifications">
            {FILTERS.map(([value, label]) => (
              <button
                key={value}
                type="button"
                role="tab"
                aria-selected={filter === value}
                className={filter === value ? "active" : ""}
                onClick={() => changeFilter(value)}
              >
                {label}
                {value === "unread" && unreadCount > 0 ? <span className="cnd-tab-count">{unreadCount}</span> : null}
              </button>
            ))}
          </div>
          <span className="cnd-notifications-total">
            <Bell size={15} /> {unreadCount} unread
          </span>
        </header>

        {loading ? (
          <div className="cnd-notification-skeletons">
            {[0, 1, 2, 3].map((row) => (
              <div key={row} className="cnd-notification-skeleton">
                <Skeleton className="cnd-skeleton-avatar" />
                <Skeleton lines={2} />
              </div>
            ))}
          </div>
        ) : error ? (
          <ErrorState message={error} onRetry={load} />
        ) : items.length === 0 ? (
          <EmptyState
            icon={Bell}
            title={filter === "unread"
              ? "No unread notifications"
              : category === "all" ? "No notifications yet" : `No ${category} notifications yet`}
            message={filter === "unread"
              ? "You are all caught up. New updates will appear here."
              : category === "all"
                ? "When someone sends an interest or your verification status changes, you will see it here."
                : "New updates in this category will appear here."}
            action={filter !== "all" ? (
              <button type="button" className="cnd-btn cnd-btn-ghost" onClick={() => changeFilter("all")}>
                View all notifications
              </button>
            ) : <a href="/candidate/matches" className="cnd-btn cnd-btn-primary">Browse matches</a>}
          />
        ) : (
          <div className="cnd-notification-groups">
            {groups.map((group) => (
              <section key={group.label} className="cnd-notification-group">
                <h3>{group.label}</h3>
                <ul>
                  {group.items.map((item) => {
                    const Icon = iconFor(item.type);
                    const profileId = Number(item.data?.profile_id || 0);
                    return (
                      <li key={item.id} className={`cnd-notification-row ${item.read ? "" : "unread"}`}>
                        <button type="button" className="cnd-notification-main" onClick={() => openNotification(item)}>
                          <span className="cnd-notification-icon"><Icon size={18} /></span>
                          <span className="cnd-notification-copy">
                            <span className="cnd-notification-title">
                              <strong>{item.title}</strong>
                              {!item.read ? <span className="cnd-unread-dot" aria-label="Unread" /> : null}
                            </span>
                            <p>{item.message}</p>
                            <small>
                              {timeAgo(item.created_at)}
                              {profileId ? " • Tap to view profile" : ""}
                            </small>
                          </span>
                        </button>
                        <div className="cnd-notification-actions">
                          {!item.read ? (
                            <button
                              type="button"
                              className="cnd-icon-btn"
                              onClick={() => markRead(item)}
                              disabled={busyId === item.id}
                              aria-label="Mark as read"
                              title="Mark as read"
                            >
                              {busyId === item.id ? <Loader2 size={16} className="cnd-spin" /> : <CheckCheck size={16} />}
                            </button>
                          ) : null}
                          <button
                            type="button"
                            className="cnd-icon-btn cnd-doc-delete"
                            onClick={() => setRemoveTarget(item)}
                            aria-label="Delete notification"
                            title="Delete notification"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              </section>
            ))}
          </div>
        )}

        {!loading && !error ? <Pagination pagination={pagination} onPage={setPage} busy={loading} /> : null}
      </section>

      <MatchDetailModal
        key={selected?.id || "closed"}
        open={Boolean(selected)}
        profileId={selected?.id}
        onClose={() => setSelected(null)}
        allowInterest={false}
      />

      <ConfirmDialog
        open={Boolean(removeTarget)}
        title="Delete this notification?"
        message="This notification will be permanently removed from your account."
        confirmLabel="Delete notification"
        busy={removing}
        onCancel={() => setRemoveTarget(null)}
        onConfirm={removeNotification}
      />
      </div>
    </CandidateLayout>
  );
}
