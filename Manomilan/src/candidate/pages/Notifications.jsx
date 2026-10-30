import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Bell, CheckCheck, CheckCircle2, Heart, Inbox, Loader2, Settings2, Trash2,
  UserRound,
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

const FILTERS = [
  ["all", "All notifications"],
  ["unread", "Unread"],
  ["read", "Read"],
];

const iconFor = (type = "") => {
  if (type.includes("interest") || type.includes("connect")) return Heart;
  if (type.includes("document") || type.includes("verified")) return CheckCircle2;
  if (type.includes("preference")) return Settings2;
  if (type.includes("profile")) return UserRound;
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
  const [filter, setFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [busyId, setBusyId] = useState(null);
  const [markingAll, setMarkingAll] = useState(false);
  const [removeTarget, setRemoveTarget] = useState(null);
  const [removing, setRemoving] = useState(false);
  const [selected, setSelected] = useState(null);
  const { toasts, push, dismiss } = useToasts();

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = await getNotifications({
        filter,
        page,
        per_page: 12,
      });
      setItems(result?.notifications || []);
      setPagination(result?.pagination || null);
      setUnreadCount(Number(result?.unread_count) || 0);
    } catch (loadError) {
      setError(loadError.message || "Could not load notifications.");
    } finally {
      setLoading(false);
    }
  }, [filter, page]);

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
  }, [load]);

  const changeFilter = (value) => {
    setFilter(value);
    setPage(1);
  };

  const markRead = async (item) => {
    if (item.read || busyId === item.id) return;
    setBusyId(item.id);
    try {
      const result = await markNotificationRead(item.id);
      setItems((current) => current.map((entry) => (
        entry.id === item.id ? { ...entry, read: true, read_at: new Date().toISOString() } : entry
      )));
      setUnreadCount(Number(result?.unread_count) || 0);
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
      <Toasts items={toasts} onDismiss={dismiss} />
      <PageHeader
        eyebrow="Notifications"
        title="Stay up to date"
        subtitle="Interests, verification updates and important account activity appear here."
        actions={(
          <button type="button" className="cnd-btn cnd-btn-primary" onClick={markAll} disabled={markingAll || !unreadCount}>
            {markingAll ? <Loader2 size={16} className="cnd-spin" /> : <CheckCheck size={16} />}
            Mark all as read
          </button>
        )}
      />

      <section className="cnd-card cnd-notifications-card">
        <header className="cnd-notifications-head">
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
            title={filter === "unread" ? "No unread notifications" : "No notifications yet"}
            message={filter === "unread"
              ? "You are all caught up. New updates will appear here."
              : "When someone sends an interest or your verification status changes, you will see it here."}
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
    </CandidateLayout>
  );
}
