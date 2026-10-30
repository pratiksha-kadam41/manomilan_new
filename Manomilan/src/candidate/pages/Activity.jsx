import { useCallback, useEffect, useMemo, useState } from "react";
import { Check, Clock3, Loader2, Search, Send, Users, X } from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import MatchDetailModal from "../components/MatchDetailModal";
import {
  Avatar, EmptyState, ErrorState, PageHeader, Pagination,
  Skeleton, StatusBadge, Toasts,
} from "../components/Ui";
import { useToasts } from "../useToasts";
import { getCachedHomeData } from "../../auth";
import { getCandidateProfile, getInterests, respondToInterest } from "../api";
import { formatDate, text } from "../utils";

export default function Activity({ currentPath, mode = "sent" }) {
  const cached = useMemo(() => getCachedHomeData(), []);
  const [candidateInfo, setCandidateInfo] = useState({
    name: cached?.profile?.full_name || cached?.user?.name || cached?.user?.username,
    id: cached?.profile?.registration_number || cached?.profile?.profile_id || cached?.user?.username,
    photo: cached?.profile?.photo_url || "",
  });
  const [records, setRecords] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [selected, setSelected] = useState(null);
  const [responding, setResponding] = useState({});
  const { toasts, dismiss } = useToasts();

  const load = useCallback(async () => {
    setLoading(true);
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
        actions={<a href="/candidate/matches" className="cnd-btn cnd-btn-primary"><Users size={17} /> Find more matches</a>}
      />

      <section className="cnd-card cnd-activity-card">
        <header className="cnd-activity-toolbar">
          <div className="cnd-segmented">
            <span className="active">Sent interests</span>
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
          <EmptyState icon={Send} title="No interests sent" message={query ? "No sent interests match this search." : "Profiles you send interest to will appear here."} action={<a href="/candidate/matches" className="cnd-btn cnd-btn-primary">Browse matches</a>} />
        ) : (
          <>
            <ul className="cnd-interest-list">
              {records.map((interest) => {
                const member = interest.profile || {};
                const name = text(member.full_name, "Member");
                return (
                  <li key={interest.id} className="cnd-interest-row">
                    <button type="button" className="cnd-interest-main" onClick={() => setSelected({ id: member.id, match: { ...member, ...interest } })}>
                      <Avatar name={name} src={member.photo_url} size="lg" />
                      <span className="cnd-interest-info">
                        <span className="cnd-interest-name"><strong>{name}</strong><StatusBadge status={interest.status || "pending"} /></span>
                        <small>{[member.age ? `${member.age} years` : "", member.city, member.profession].filter(Boolean).join(" • ") || "Profile details added"}</small>
                        <span className="cnd-interest-meta"><span>{interest.match_percentage || 0}% match</span><span><Clock3 size={13} /> {formatDate(interest.created_at, "Recently")}</span></span>
                      </span>
                    </button>
                    <div className="cnd-interest-actions">
                      <button type="button" className="cnd-btn cnd-btn-ghost" onClick={() => setSelected({ id: member.id, match: { ...member, ...interest } })}>View profile</button>
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

      <MatchDetailModal key={selected?.id || "closed"} open={Boolean(selected)} profileId={selected?.id} initialMatch={selected?.match} onClose={() => setSelected(null)} allowInterest />
    </CandidateLayout>
  );
}
