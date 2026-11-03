import { useCallback, useEffect, useRef, useState } from "react";
import {
  Filter, Loader2, RotateCcw, Search, SlidersHorizontal, Users,
} from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import MatchResultCard from "../components/MatchResultCard";
import {
  CardGridSkeleton, EmptyState, ErrorState, FormField, PageHeader,
  Toasts,
} from "../components/Ui";
import { useToasts } from "../useToasts";
import { Link, useLocation, useNavigate } from "react-router-dom";
import {
  cacheCandidateFeed, getCachedCandidateFeed, getCachedMatches, getMatches, sendInterest,
} from "../api";
import { useProfileCompletion } from "../profile-completion-context";
import { humanGender, text } from "../utils";
import "./Matches.css";

const EMPTY_FILTERS = {
  q: "",
  age_min: "",
  age_max: "",
  religion: "",
  caste: "",
  city: "",
  education: "",
  profession: "",
  marital_status: "",
  sort: "best_match",
};

const SORTS = [
  ["best_match", "Best match"],
  ["newest", "Newest profiles"],
  ["age_asc", "Age: low to high"],
  ["age_desc", "Age: high to low"],
];

export default function Matches() {
  const navigate = useNavigate();
  const location = useLocation();
  const requestedSort = new URLSearchParams(location.search).get("sort");
  const validRequestedSort = SORTS.some(([value]) => value === requestedSort) ? requestedSort : null;
  const [initialFeed] = useState(() => {
    const cachedFeed = getCachedCandidateFeed();
    return validRequestedSort && cachedFeed?.feed_filters?.sort !== validRequestedSort
      ? null
      : cachedFeed;
  });
  const [initialFilters] = useState(() => (
    initialFeed?.feed_filters || { ...EMPTY_FILTERS, ...(validRequestedSort ? { sort: validRequestedSort } : {}) }
  ));
  const [initialMatches] = useState(() => (
    initialFeed || getCachedMatches({
      ...initialFilters, page: 1, per_page: 12, feed: 1, include_filters: 1,
    })
  ));
  const profileCompletion = useProfileCompletion();
  const [draft, setDraft] = useState(initialFilters);
  const [applied, setApplied] = useState(initialFilters);
  const [page, setPage] = useState(initialMatches?.pagination?.current_page || 1);
  const [matches, setMatches] = useState(initialMatches?.matches || []);
  const [options, setOptions] = useState(initialMatches?.filters || {});
  const [pagination, setPagination] = useState(initialMatches?.pagination || null);
  const [loading, setLoading] = useState(!initialMatches);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState("");
  const [loadMoreError, setLoadMoreError] = useState("");
  const [busy, setBusy] = useState({});
  const [showMoreFilters, setShowMoreFilters] = useState(false);
  const loadRequestId = useRef(0);
  const loadAbortController = useRef(null);
  const loadedIds = useRef(new Set((initialMatches?.matches || []).map((item) => String(item.id))));
  const loadingMoreRef = useRef(false);
  const feedSentinel = useRef(null);
  const { toasts, push, dismiss } = useToasts();

  const load = useCallback(async () => {
    loadAbortController.current?.abort();
    const controller = new AbortController();
    loadAbortController.current = controller;
    const requestId = ++loadRequestId.current;
    const params = {
      ...applied,
      page,
      per_page: 12,
      feed: 1,
      include_filters: page === 1 ? 1 : 0,
    };
    if (page > 1 && loadedIds.current.size) {
      params.exclude_ids = [...loadedIds.current].join(",");
    }
    const cachedMatches = page === 1 ? getCachedMatches(params) : null;
    if (cachedMatches) {
      setMatches(cachedMatches.matches || []);
      setOptions(cachedMatches.filters || {});
      setPagination(cachedMatches.pagination || null);
      setLoading(false);
      loadedIds.current = new Set((cachedMatches.matches || []).map((item) => String(item.id)));
    } else if (page === 1) {
      setLoading(true);
    } else {
      setLoadingMore(true);
    }
    setError("");
    setLoadMoreError("");
    try {
      const result = await getMatches(params, { signal: controller.signal });
      if (requestId !== loadRequestId.current) return;
      const nextMatches = page === 1
        ? result?.matches || []
        : (result?.matches || []).filter((item) => {
          const id = String(item.id);
          if (loadedIds.current.has(id)) return false;
          loadedIds.current.add(id);
          return true;
        });
      if (page === 1) {
        loadedIds.current = new Set(nextMatches.map((item) => String(item.id)));
        setMatches(nextMatches);
      } else {
        setMatches((current) => [...current, ...nextMatches]);
      }
      cacheCandidateFeed(applied, result, page > 1);
      if (page === 1 && result?.filters) setOptions(result.filters);
      setPagination(result?.pagination || null);
    } catch (loadError) {
      if (requestId !== loadRequestId.current) return;
      const message = loadError.message || "Could not load compatible profiles.";
      if (page === 1) setError(message);
      else setLoadMoreError(message);
    } finally {
      if (requestId === loadRequestId.current) {
        setLoading(false);
        setLoadingMore(false);
        loadingMoreRef.current = false;
      }
    }
  }, [applied, page]);

  useEffect(() => {
    const restoredFeedIsCurrent = initialFeed
      && page === (initialFeed.pagination?.current_page || 1)
      && JSON.stringify(applied) === JSON.stringify(initialFilters);
    if (restoredFeedIsCurrent) return undefined;
    if (profileCompletion.status !== "complete") return;
    Promise.resolve().then(load);
    return () => {
      loadAbortController.current?.abort();
      loadRequestId.current += 1;
    };
  }, [applied, initialFeed, initialFilters, load, page, profileCompletion.status]);

  const hasMore = Boolean(pagination?.has_more);
  useEffect(() => {
    const sentinel = feedSentinel.current;
    if (!sentinel || !hasMore || loading || loadingMore || profileCompletion.status !== "complete") return undefined;
    const observer = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting || loadingMoreRef.current) return;
      loadingMoreRef.current = true;
      setLoadingMore(true);
      setPage((current) => current + 1);
    }, { rootMargin: "500px 0px" });
    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [hasMore, loading, loadingMore, profileCompletion.status]);

  useEffect(() => {
    if (!showMoreFilters) return undefined;
    const closeOnEscape = (event) => {
      if (event.key === "Escape") setShowMoreFilters(false);
    };
    window.addEventListener("keydown", closeOnEscape);
    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [showMoreFilters]);

  const updateDraft = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
  const closeFilters = () => setShowMoreFilters(false);

  const applyFilters = (event) => {
    event?.preventDefault();
    loadedIds.current = new Set();
    setMatches([]);
    setPagination(null);
    setError("");
    setLoadMoreError("");
    setPage(1);
    setApplied({ ...draft });
    closeFilters();
  };

  const clearFilters = () => {
    const changed = page !== 1 || Object.keys(EMPTY_FILTERS).some((key) => (
      applied[key] !== EMPTY_FILTERS[key]
    ));
    setDraft(EMPTY_FILTERS);
    if (!changed) {
      closeFilters();
      return;
    }
    loadedIds.current = new Set();
    setMatches([]);
    setPagination(null);
    setError("");
    setLoadMoreError("");
    setApplied(EMPTY_FILTERS);
    setPage(1);
    closeFilters();
  };

  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (draft.q === applied.q && draft.sort === applied.sort) return;
      loadedIds.current = new Set();
      setMatches([]);
      setPagination(null);
      setError("");
      setLoadMoreError("");
      setPage(1);
      setApplied((current) => ({ ...current, q: draft.q, sort: draft.sort }));
    }, 350);
    return () => window.clearTimeout(timer);
  }, [draft.q, draft.sort, applied.q, applied.sort]);

  const updateMatch = (profileId, patch) => {
    setMatches((items) => items.map((item) => (
      item.id === profileId ? { ...item, ...patch } : item
    )));
  };

  const handleInterest = async (match) => {
    if (busy[match.id]) return;
    setBusy((state) => ({ ...state, [match.id]: true }));
    try {
      const result = await sendInterest(match.id);
      updateMatch(match.id, { interest_id: result?.interest?.id || -1 });
      push(`Interest sent to ${text(match.full_name, "this profile")}.`);
    } catch (sendError) {
      if (sendError.status === 409) {
        updateMatch(match.id, { interest_id: -1 });
        push("You have already sent an interest to this profile.", "info");
      } else {
        push(sendError.message || "Could not send the interest.", "error");
      }
    } finally {
      setBusy((state) => {
        const next = { ...state };
        delete next[match.id];
        return next;
      });
    }
  };

  const activeFilterCount = Object.entries(applied)
    .filter(([key, value]) => key !== "sort" && value !== "")
    .length;
  const targetGender = profileCompletion.gender === "groom"
    ? "bride"
    : profileCompletion.gender === "bride" ? "groom" : "";
  const resultLabel = targetGender ? `${humanGender(targetGender)} profiles` : "Compatible profiles";
  const retryLoadingMore = () => {
    setLoadMoreError("");
    loadingMoreRef.current = true;
    setLoadingMore(true);
    load();
  };

  return (
    <CandidateLayout currentPath="/candidate/matches">
      <div className={`find-matches-page ${showMoreFilters ? "filters-open" : ""}`}>
        <Toasts items={toasts} onDismiss={dismiss} />
        <PageHeader
          eyebrow="Discover"
          title="Find your match"
          subtitle="Search verified profiles ranked by your partner preferences and compatibility."
          actions={(
            <button type="button" className="cnd-btn cnd-btn-ghost" onClick={clearFilters}>
              <RotateCcw size={16} /> Reset filters
            </button>
          )}
        />

        {profileCompletion.status === "checking" || profileCompletion.status === "idle" ? (
          <CardGridSkeleton count={6} />
        ) : profileCompletion.status === "error" ? (
          <EmptyState
            icon={Users}
            title="Unable to check profile completion"
            message="Your candidate page is still available. Try again shortly, or open your profile to continue."
            action={<Link to="/candidate/profile" className="cnd-btn cnd-btn-primary">Open profile</Link>}
          />
        ) : profileCompletion.status !== "complete" ? (
          <EmptyState
            icon={Users}
            title={profileCompletion.hasProfile ? "Complete your profile first" : "Create your profile first"}
            message="Complete your profile to unlock compatible matches and recommendations."
            action={(
              <Link to="/candidate/profile" className="cnd-btn cnd-btn-primary">
                {profileCompletion.hasProfile ? "Complete profile" : "Create profile"}
              </Link>
            )}
          />
        ) : (
          <>
          <section className="cnd-card cnd-filter-card">
            <header className="cnd-filter-head">
              <div>
                <span className="cnd-card-icon"><Filter size={18} /></span>
                <div>
                  <h2>Filter matches</h2>
                  <p>{activeFilterCount ? `${activeFilterCount} filters applied` : "Use filters to narrow down your search"}</p>
                </div>
              </div>
            </header>

            <form className="cnd-filter-form" onSubmit={applyFilters}>
              <FormField label="Search" name="match-q" className="cnd-match-search-field">
                <div className="cnd-input-with-icon">
                  <Search size={17} />
                  <input
                    id="match-q"
                    type="search"
                    value={draft.q}
                    onChange={(event) => updateDraft("q", event.target.value)}
                    placeholder="Name, profile ID, city or profession"
                  />
                </div>
              </FormField>

              <button
                type="button"
                className="cnd-btn cnd-btn-ghost cnd-more-filters-button"
                onClick={() => setShowMoreFilters((current) => !current)}
                aria-expanded={showMoreFilters}
                aria-controls="find-matches-filter-panel"
              >
                <SlidersHorizontal size={16} />
                {showMoreFilters ? "Hide filters" : "More filters"}
                {activeFilterCount ? <span className="cnd-more-filter-count">{activeFilterCount}</span> : null}
              </button>

              {showMoreFilters ? (
              <>
              <button
                type="button"
                className="find-matches-filter-backdrop"
                onClick={closeFilters}
                aria-label="Close filters"
                tabIndex={-1}
              />
              <div
                id="find-matches-filter-panel"
                className="cnd-advanced-filter-grid find-matches-filter-panel"
                role="region"
                aria-label="Match filters"
              >
                <div className="find-matches-filter-panel-head">
                  <strong>Refine your matches</strong>
                  <button type="button" className="cnd-icon-btn" onClick={closeFilters} aria-label="Close filters">×</button>
                </div>
              <FormField label="Sort by" name="match-sort">
                <select id="match-sort" value={draft.sort} onChange={(event) => updateDraft("sort", event.target.value)}>
                  {SORTS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
              </FormField>

              <div className="cnd-age-range">
                <FormField label="Age from" name="age-min">
                  <input
                    id="age-min"
                    type="number"
                    min="18"
                    max="100"
                    value={draft.age_min}
                    onChange={(event) => updateDraft("age_min", event.target.value)}
                    placeholder="18"
                  />
                </FormField>
                <FormField label="Age to" name="age-max">
                  <input
                    id="age-max"
                    type="number"
                    min="18"
                    max="100"
                    value={draft.age_max}
                    onChange={(event) => updateDraft("age_max", event.target.value)}
                    placeholder="40"
                  />
                </FormField>
              </div>

              <FormField label="Religion" name="religion">
                <select id="religion" value={draft.religion} onChange={(event) => updateDraft("religion", event.target.value)}>
                  <option value="">Any religion</option>
                  {(options.religions || []).map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
              </FormField>

              <FormField label="Community" name="caste">
                <select id="caste" value={draft.caste} onChange={(event) => updateDraft("caste", event.target.value)}>
                  <option value="">Any community</option>
                  {(options.castes || []).map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
              </FormField>

              <FormField label="City" name="match-city">
                <select id="match-city" value={draft.city} onChange={(event) => updateDraft("city", event.target.value)}>
                  <option value="">Any city</option>
                  {(options.cities || []).map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
              </FormField>

              <FormField label="Education" name="match-education">
                <select id="match-education" value={draft.education} onChange={(event) => updateDraft("education", event.target.value)}>
                  <option value="">Any education</option>
                  {(options.educations || []).map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
              </FormField>

              <FormField label="Profession" name="match-profession">
                <input
                  id="match-profession"
                  type="search"
                  value={draft.profession}
                  onChange={(event) => updateDraft("profession", event.target.value)}
                  placeholder="Any profession"
                />
              </FormField>

              <FormField label="Marital status" name="marital-status">
                <select id="marital-status" value={draft.marital_status} onChange={(event) => updateDraft("marital_status", event.target.value)}>
                  <option value="">Any status</option>
                  {(options.marital_statuses || []).map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
              </FormField>
              </div>
              </>
              ) : null}

              <div className="cnd-filter-actions">
                <button type="button" className="cnd-btn cnd-btn-ghost" onClick={clearFilters}>Clear</button>
                <button type="submit" className="cnd-btn cnd-btn-primary" disabled={loading}>
                  {loading ? <Loader2 size={16} className="cnd-spin" /> : <Search size={16} />}
                  Show matches
                </button>
              </div>
            </form>
          </section>

          <div className="cnd-results-head">
            <div>
              <h2>{resultLabel}</h2>
              <p>
                {loading ? "Finding compatible profiles..." : pagination
                  ? `${pagination.total.toLocaleString("en-IN")} profiles found`
                  : "Browse compatible profiles"}
              </p>
            </div>
            {activeFilterCount ? <span className="cnd-chip">{activeFilterCount} active filters</span> : null}
          </div>

          {error ? (
            <ErrorState message={error} onRetry={load} />
          ) : loading && !matches.length ? (
            <CardGridSkeleton count={6} />
          ) : matches.length === 0 ? (
            <EmptyState
              icon={Users}
              title="No matches found"
              message="No profiles match these filters. Try widening your age range or clearing a filter."
              action={<button type="button" className="cnd-btn cnd-btn-primary" onClick={clearFilters}>Clear filters</button>}
            />
          ) : (
            <>
              <div className="cnd-match-grid">
                {matches.map((match) => (
                  <MatchResultCard
                    key={match.id}
                    match={match}
                    detailed
                    onOpen={(item) => navigate(`/candidate/matches/${item.id}`)}
                    onInterest={handleInterest}
                    interestBusy={Boolean(busy[match.id])}
                  />
                ))}
              </div>
              {loadMoreError ? (
                <ErrorState message={loadMoreError} onRetry={retryLoadingMore} />
              ) : null}
              {loadingMore ? <CardGridSkeleton count={3} /> : null}
              {hasMore ? <div ref={feedSentinel} className="cnd-feed-sentinel" aria-hidden="true" /> : null}
            </>
          )}
        </>
      )}
      </div>
    </CandidateLayout>
  );
}
