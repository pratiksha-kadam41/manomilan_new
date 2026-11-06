import { useCallback, useEffect, useMemo, useState } from "react";
import { Check, Clock3, Loader2, Search, Send, Users, X } from "lucide-react";
import { Link } from "react-router-dom";
import CandidateLayout from "../components/CandidateLayout";
import MatchDetailModal from "../components/MatchDetailModal";
import {
  Avatar, EmptyState, ErrorState, PageHeader, Pagination,
  Skeleton, StatusBadge, Toasts,
} from "../components/Ui";
import { useToasts } from "../useToasts";
import { getCachedHomeData } from "../../auth";
import {
  getCachedInterests, getCandidateProfile, getInterests, respondToInterest,
} from "../api";
import { formatDate, text } from "../utils";

export default function Activity({ currentPath, mode = "sent" }) {
  const cached = useMemo(() => getCachedHomeData(), []);
  const initialInterests = useMemo(
    () => getCachedInterests(mode, { q: "", page: 1, per_page: 10 }),
    [mode],
  );
  const [candidateInfo, setCandidateInfo] = useState({
    name: cached?.profile?.full_name || cached?.user?.name || cached?.user?.username,
    id: cached?.profile?.registration_number || cached?.profile?.profile_id || cached?.user?.username,
    photo: cached?.profile?.photo_url || "",
  });
  const [records, setRecords] = useState(initialInterests?.interests || []);
  const [pagination, setPagination] = useState(initialInterests?.pagination || null);
  const [counts, setCounts] = useState(initialInterests?.counts || {});
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(!initialInterests);
  const [error, setError] = useState("");
  const [selected, setSelected] = useState(null);
  const [responding, setResponding] = useState({});
  const { toasts, push, dismiss } = useToasts();

  const load = useCallback(async () => {
    const cachedInterests = getCachedInterests(mode, { q: query, page, per_page: 10 });
    if (cachedInterests) {
      setRecords(cachedInterests.interests || []);
      setPagination(cachedInterests.pagination || null);
      setCounts(cachedInterests.counts || {});
      setLoading(false);
    } else {
      setLoading(true);
    }
    setError("");
    try {
      const [profileResult, interestsResult] = await Promise.all([
        getCandidateProfile().catch(() => null),
        getInterests(mode, { q: query, page, per_page: 10 }),
      ]);

      if (profileResult?.profile) {
        setCandidateInfo({
          name: profileResult.profile.full_name || profileResult.user?.name,
          id: profileResult.profile.registration_number || profileResult.profile.profile_id || profileResult.user?.username,
          photo: profileResult.profile.photo_url || "",
        });
      }
      setRecords(interestsResult?.interests || []);
      setPagination(interestsResult?.pagination || null);
      setCounts(interestsResult?.counts || {});
    } catch (loadError) {
      setError(loadError.message || "Could not load sent interests.");
    } finally {
      setLoading(false);
    }
  }, [mode, page, query]);

  useEffect(() => {
    Promise.resolve().then(load);
  }, [load]);

  const submitSearch = (event) => {
    event.preventDefault();
    setPage(1);
    setQuery(search.trim());
  };

  const respond = async (interest, status) => {
    setResponding((current) => ({ ...current, [interest.id]: true }));
    try {
      await respondToInterest(interest.id, status);
      setRecords((current) => current.map((item) => (
        item.id === interest.id ? { ...item, status } : item
      )));
    } catch (respondError) {
      push(respondError.message || "Could not update this interest.", "error");
    } finally {
      setResponding((current) => {
        const next = { ...current };
        delete next[interest.id];
        return next;
      });
    }
  };

  return (
    <CandidateLayout currentPath={currentPath} candidateInfo={candidateInfo}>
      <Toasts items={toasts} onDismiss={dismiss} />
      <PageHeader
        eyebrow="Interests"
        title={mode === "received" ? "Interests Received" : "Interests Sent"}
        subtitle={mode === "received" ? "Review interests sent by other candidates." : "Profiles you have sent interest to."}
        actions={<Link to="/candidate/matches" className="cnd-btn cnd-btn-primary"><Users size={17} /> Find more matches</Link>}
      />

      <section className="cnd-card cnd-activity-card">
        <header className="cnd-activity-toolbar">
          <div className="cnd-segmented" aria-label="Interest type">
            <Link className={mode === "received" ? "active" : ""} to="/candidate/interests-received">
              Received{counts.received ? ` (${counts.received})` : ""}
            </Link>
            <Link className={mode === "sent" ? "active" : ""} to="/candidate/interests-sent">
              Sent{counts.sent ? ` (${counts.sent})` : ""}
            </Link>
          </div>
          <form className="cnd-search-box" onSubmit={submitSearch}>
            <Search size={16} />
            <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search by name or city" aria-label="Search interests" />
            <button type="submit" className="cnd-btn cnd-btn-ghost">Search</button>
          </form>
        </header>

        {loading ? (
          <div className="cnd-interest-list">
            {[0, 1, 2].map((row) => (
              <div key={row} className="cnd-interest-skeleton"><Skeleton className="cnd-skeleton-avatar" /><Skeleton lines={2} /></div>
            ))}
          </div>
        ) : error ? (
          <ErrorState message={error} onRetry={load} />
        ) : records.length === 0 ? (
          <EmptyState
            icon={Send}
            title={mode === "received" ? "No interests received yet" : "No interests sent yet"}
            message={query
              ? `No ${mode} interests match this search.`
              : mode === "received"
                ? "When another candidate sends you an interest, it will appear here."
                : "Profiles you send interest to will appear here."}
            action={<Link to="/candidate/matches" className="cnd-btn cnd-btn-primary">Browse matches</Link>}
          />
        ) : (
          <>
            <ul className="cnd-interest-list">
              {records.map((interest) => {
                const member = interest.profile || {};
                const name = text(member.full_name, "Member");
                const status = String(interest.status || "pending").toLowerCase();
                const statusText = status === "pending" ? "Awaiting response" : status === "accepted" ? "Accepted" : "Declined";
                const openProfile = () => {
                  if (member.id) setSelected({ id: member.id, match: { ...member, ...interest } });
                };
                return (
                  <li key={interest.id} className="cnd-interest-row">
                    <button type="button" className="cnd-interest-main" onClick={openProfile} disabled={!member.id}>
                      <Avatar name={name} src={member.photo_url} size="lg" />
                      <span className="cnd-interest-info">
                        <span className="cnd-interest-name"><strong>{name}</strong><StatusBadge status={interest.status || "pending"}>{statusText}</StatusBadge></span>
                        <small>{[member.age ? `${member.age} years` : "", member.city, member.profession].filter(Boolean).join(" • ") || (member.id ? "Profile details added" : "Profile details not available yet")}</small>
                        <span className="cnd-interest-meta"><span>{interest.match_percentage || 0}% match</span><span><Clock3 size={13} /> {formatDate(interest.created_at, "Recently")}</span></span>
                      </span>
                    </button>
                    <div className="cnd-interest-actions">
                      {member.id ? (
                        <button type="button" className="cnd-btn cnd-btn-ghost" onClick={openProfile}>View profile</button>
                      ) : null}
                      {mode === "received" && String(interest.status || "pending").toLowerCase() === "pending" ? (
                        <>
                          <button type="button" className="cnd-btn cnd-btn-primary" onClick={() => respond(interest, "accepted")} disabled={responding[interest.id]}>
                            {responding[interest.id] ? <Loader2 size={16} className="cnd-spin" /> : <Check size={16} />} Accept
                          </button>
                          <button type="button" className="cnd-btn cnd-btn-danger" onClick={() => respond(interest, "rejected")} disabled={responding[interest.id]}>
                            <X size={16} /> Reject
                          </button>
                        </>
                      ) : null}
                    </div>
                  </li>
                );
              })}
            </ul>
            <Pagination pagination={pagination} onPage={setPage} busy={loading} />
          </>
        )}
      </section>

      <MatchDetailModal key={selected?.id || "closed"} open={Boolean(selected)} profileId={selected?.id} initialMatch={selected?.match} onClose={() => setSelected(null)} allowInterest={mode === "received" ? false : true} />
    </CandidateLayout>
  );
}
