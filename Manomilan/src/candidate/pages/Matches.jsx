import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Filter, Loader2, RotateCcw, Search, SlidersHorizontal, Users,
} from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import MatchResultCard from "../components/MatchResultCard";
import MatchDetailModal from "../components/MatchDetailModal";
import {
  CardGridSkeleton, EmptyState, ErrorState, FormField, PageHeader, Pagination,
  Toasts,
} from "../components/Ui";
import { useToasts } from "../useToasts";
import { getCachedHomeData } from "../../auth";
import { getCandidateProfile, getMatches, sendInterest } from "../api";
import { humanGender, text } from "../utils";

const EMPTY_FILTERS = {
  q: "",
  age_min: "",
  age_max: "",
  religion: "",
  caste: "",
  city: "",
  education: "",
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
  const cached = useMemo(() => getCachedHomeData(), []);
  const [profile, setProfile] = useState(cached?.profile || null);
  const [account, setAccount] = useState(cached?.user || null);
  const [draft, setDraft] = useState(EMPTY_FILTERS);
  const [applied, setApplied] = useState(EMPTY_FILTERS);
  const [page, setPage] = useState(1);
  const [matches, setMatches] = useState([]);
  const [options, setOptions] = useState({});
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState({});
  const [selected, setSelected] = useState(null);
  const [profileChecked, setProfileChecked] = useState(Boolean(cached?.profile));
  const [showMoreFilters, setShowMoreFilters] = useState(false);
  const { toasts, push, dismiss } = useToasts();

  useEffect(() => {
    let active = true;
    getCandidateProfile()
      .then((result) => {
        if (!active) return;
        setProfile(result?.profile || null);
        setAccount(result?.user || null);
      })
      .catch(() => {})
      .finally(() => {
        if (active) setProfileChecked(true);
      });
    return () => {
      active = false;
    };
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = await getMatches({ ...applied, page, per_page: 12 });
      setMatches(result?.matches || []);
      setOptions(result?.filters || {});
      setPagination(result?.pagination || null);
    } catch (loadError) {
      setError(loadError.message || "Could not load compatible profiles.");
    } finally {
      setLoading(false);
    }
  }, [applied, page]);

  useEffect(() => {
    if (!profileChecked || !profile) return;
    Promise.resolve().then(load);
  }, [load, profile, profileChecked]);

  const updateDraft = (key, value) => setDraft((current) => ({ ...current, [key]: value }));

  const applyFilters = (event) => {
    event?.preventDefault();
    setPage(1);
    setApplied({ ...draft });
  };

  const clearFilters = () => {
    setDraft(EMPTY_FILTERS);
    setApplied(EMPTY_FILTERS);
    setPage(1);
  };

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setPage(1);
      setApplied((current) => ({ ...current, q: draft.q, sort: draft.sort }));
    }, 350);
    return () => window.clearTimeout(timer);
  }, [draft.q, draft.sort]);

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

  const candidateInfo = {
    name: profile?.full_name || account?.name || account?.username,
    id: profile?.registration_number || profile?.profile_id || account?.username,
    photo: profile?.photo_url || "",
  };

  const activeFilterCount = Object.entries(applied)
    .filter(([key, value]) => key !== "sort" && value !== "")
    .length;
  const targetGender = profile?.gender === "groom" ? "bride" : profile?.gender === "bride" ? "groom" : "";
  const resultLabel = targetGender ? `${humanGender(targetGender)} profiles` : "Compatible profiles";

  return (
    <CandidateLayout currentPath="/candidate/matches" candidateInfo={candidateInfo}>
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

      {!profileChecked ? (
        <CardGridSkeleton count={6} />
      ) : !profile ? (
        <EmptyState
          icon={Users}
          title="Create your profile first"
          message="Add your basic details and partner preferences so we can show compatible matches."
          action={<a href="/candidate/profile" className="cnd-btn cnd-btn-primary">Create profile</a>}
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
              >
                <SlidersHorizontal size={16} />
                {showMoreFilters ? "Hide filters" : "More filters"}
                {activeFilterCount ? <span className="cnd-more-filter-count">{activeFilterCount}</span> : null}
              </button>

              {showMoreFilters ? (
              <div className="cnd-advanced-filter-grid">
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

              <FormField label="Marital status" name="marital-status">
                <select id="marital-status" value={draft.marital_status} onChange={(event) => updateDraft("marital_status", event.target.value)}>
                  <option value="">Any status</option>
                  {(options.marital_statuses || []).map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
              </FormField>
              </div>
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
          ) : loading ? (
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
                    onOpen={(item) => setSelected({ id: item.id, match: item })}
                    onInterest={handleInterest}
                    interestBusy={Boolean(busy[match.id])}
                  />
                ))}
              </div>
              <Pagination pagination={pagination} onPage={setPage} busy={loading} />
            </>
          )}
        </>
      )}

      <MatchDetailModal
        key={selected?.id || "closed"}
        open={Boolean(selected)}
        profileId={selected?.id}
        initialMatch={selected?.match}
        onClose={() => setSelected(null)}
        onUpdated={(updated) => updateMatch(updated.id, updated)}
      />
    </CandidateLayout>
  );
}
