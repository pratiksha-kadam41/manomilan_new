import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  ArrowLeft, BadgeCheck, Briefcase, GraduationCap, Heart, Loader2,
  MapPin, Pause, Play, ChevronLeft, ChevronRight, X,
} from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import { getCandidateProfile, getMatch, sendInterest } from "../api";
import { Avatar, ErrorState, Skeleton } from "../components/Ui";
import { displayValue, humanGender, text } from "../utils";

const value = (item) => text(item) ? item : null;

export default function CandidateProfileView({ params = {} }) {
  const { id } = params;
  const navigate = useNavigate();
  const [candidate, setCandidate] = useState(null);
  const [ownProfile, setOwnProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [interestBusy, setInterestBusy] = useState(false);
  const [activePhotoIndex, setActivePhotoIndex] = useState(0);
  const [slideshow, setSlideshow] = useState(false);
  const [slideshowFullscreen, setSlideshowFullscreen] = useState(false);

  useEffect(() => {
    let active = true;
    Promise.all([
      getMatch(id),
      getCandidateProfile().catch(() => null),
    ])
      .then(([matchResult, profileResult]) => {
        if (!active) return;
        setCandidate(matchResult?.profile || null);
        setOwnProfile(profileResult?.profile || null);
      })
      .catch((loadError) => {
        if (active) setError(loadError.message || "Could not load this profile.");
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [id]);

  const photos = useMemo(() => {
    if (!candidate) return [];
    return [
      candidate.photo_url,
      candidate.full_body_photo_url,
      candidate.half_body_photo_url,
      candidate.selfie_photo_url,
      ...(candidate.additional_photo_urls || []),
      ...(candidate.candidate_photo_urls || []),
    ].filter((photo, index, all) => photo && all.indexOf(photo) === index);
  }, [candidate]);

  useEffect(() => {
    setActivePhotoIndex(0);
    setSlideshow(false);
    setSlideshowFullscreen(false);
  }, [id]);

  useEffect(() => {
    if (!slideshow || photos.length < 2) return undefined;
    const timer = window.setInterval(() => {
      setActivePhotoIndex((current) => (current + 1) % photos.length);
    }, 2500);
    return () => window.clearInterval(timer);
  }, [photos.length, slideshow]);

  useEffect(() => {
    if (!slideshowFullscreen) return undefined;
    document.body.style.overflow = "hidden";
    const closeOnEscape = (event) => {
      if (event.key === "Escape") {
        setSlideshow(false);
        setSlideshowFullscreen(false);
      }
    };
    window.addEventListener("keydown", closeOnEscape);
    return () => {
      document.body.style.overflow = "";
      window.removeEventListener("keydown", closeOnEscape);
    };
  }, [slideshowFullscreen]);

  const movePhoto = (direction) => {
    setSlideshow(false);
    setActivePhotoIndex((current) => (
      (current + direction + photos.length) % photos.length
    ));
  };

  const closeSlideshow = () => {
    setSlideshow(false);
    setSlideshowFullscreen(false);
  };

  const details = candidate?.details || {};
  const sections = [
    {
      title: "Basic details",
      rows: [
        ["Registration number", candidate?.registration_number],
        ["Gender", humanGender(candidate?.gender)],
        ["Age", candidate?.age ? `${candidate.age} years` : ""],
        ["Birth date", candidate?.birth_date],
        ["Height", candidate?.height],
        ["Weight", details.weight],
        ["Blood group", details.blood_group],
        ["Marital status", candidate?.marital_status],
        ["Religion", candidate?.religion],
        ["Community", candidate?.caste],
        ["Sub community", details.sub_caste],
        ["Mother tongue", candidate?.mother_tongue],
        ["Languages", details.language],
        ["Current location", candidate?.city],
        ["Address", details.address],
        ["Permanent address", details.permanent_address],
      ],
    },
    {
      title: "Education",
      rows: [
        ["Education", candidate?.education],
        ["Degree / diploma", candidate?.degree],
        ["Additional degree", details.degree_2],
        ["Specialisation", details.specialisation],
        ["College / university", candidate?.college],
        ["City of education", details.city_of_education],
      ],
    },
    {
      title: "Career",
      rows: [
        ["Profession", candidate?.profession],
        ["Working field", candidate?.field_of_working],
        ["Organisation", candidate?.organisation],
        ["Designation", candidate?.designation],
        ["Employment length", details.length_of_employment],
        ["Professional city", details.professional_city],
        ["Monthly income", details.monthly_income],
        ["Annual income", candidate?.annual_income],
      ],
    },
    {
      title: "Family",
      rows: [
        ["Father", details.father_name],
        ["Father alive", details.father_alive],
        ["Father occupation", details.father_occupation],
        ["Mother", details.mother_name],
        ["Mother alive", details.mother_alive],
        ["Mother occupation", details.mother_occupation],
        ["Brothers", details.brothers],
        ["Brothers married", details.brothers_married],
        ["Sisters", details.sisters],
        ["Sisters married", details.sisters_married],
        ["Family values", details.family_values],
        ["Financial status", details.family_financial_status],
        ["Annual family income", details.family_annual_income],
      ],
    },
    {
      title: "Lifestyle and interests",
      rows: [
        ["Diet", candidate?.diet],
        ["Smoking", candidate?.smoking],
        ["Drinking", candidate?.drinking],
        ["Partying", details.partying],
        ["Hobbies", candidate?.hobbies],
        ["Extra curricular activities", details.extra_curricular],
        ["Achievements", details.achievements],
        ["Sports and fitness", details.sports],
        ["Cooking skills", details.cooking_skills],
        ["Instagram", details.instagram],
        ["Facebook", details.facebook],
      ],
    },
    {
      title: "Birth and horoscope",
      rows: [
        ["Place of birth", details.place_of_birth],
        ["Time of birth", details.time_of_birth],
        ["Rashi", details.rashi],
        ["Nakshatra", details.nakshatra],
        ["Gotra", details.gotra],
        ["Nadi", details.nadi],
      ],
    },
  ];

  const handleInterest = async () => {
    if (!candidate || candidate.interest_id) return;
    setInterestBusy(true);
    try {
      const result = await sendInterest(candidate.id);
      setCandidate((current) => ({ ...current, interest_id: result?.interest?.id || -1 }));
    } finally {
      setInterestBusy(false);
    }
  };

  const candidateInfo = {
    name: ownProfile?.full_name,
    id: ownProfile?.registration_number || ownProfile?.profile_id,
    photo: ownProfile?.photo_url || "",
  };

  return (
    <CandidateLayout currentPath="/candidate/matches" candidateInfo={candidateInfo}>
      <div className="cnd-profile-view">
        <button type="button" className="cnd-btn cnd-btn-ghost" onClick={() => navigate(-1)}>
          <ArrowLeft size={17} /> Back to matches
        </button>

        {error ? <ErrorState message={error} /> : loading ? (
          <div className="cnd-profile-view-loading"><Skeleton className="cnd-skeleton-modal-photo" /><Skeleton lines={8} /></div>
        ) : !candidate ? (
          <ErrorState message="This candidate profile is unavailable." />
        ) : (
          <>
            <section className="cnd-profile-hero">
              <div className="cnd-profile-hero-photo">
                {candidate.photo_url ? <img src={candidate.photo_url} alt={text(candidate.full_name, "Candidate")} /> : <Avatar name={candidate.full_name} size="xl" />}
              </div>
              <div className="cnd-profile-hero-copy">
                <div className="cnd-profile-name">
                  <h1>{text(candidate.full_name, "Candidate")}</h1>
                  {candidate.verified ? <span><BadgeCheck size={17} /> Verified</span> : null}
                </div>
                <p>{[candidate.age ? `${candidate.age} years` : "", humanGender(candidate.gender), candidate.city].filter(Boolean).join(" • ")}</p>
                <div className="cnd-profile-tags">
                  {candidate.profession ? <span><Briefcase size={15} /> {candidate.profession}</span> : null}
                  {candidate.education ? <span><GraduationCap size={15} /> {candidate.education}</span> : null}
                  {candidate.city ? <span><MapPin size={15} /> {candidate.city}</span> : null}
                </div>
              </div>
              <button
                type="button"
                className={`cnd-btn cnd-btn-primary cnd-profile-interest ${candidate.interest_id ? "done" : ""}`}
                onClick={handleInterest}
                disabled={Boolean(candidate.interest_id) || interestBusy}
              >
                {interestBusy ? <Loader2 size={17} className="cnd-spin" /> : <Heart size={17} />}
                {candidate.interest_id ? "Interest sent" : "Send interest"}
              </button>
            </section>

            {value(candidate.about) ? (
              <section className="cnd-card cnd-profile-section cnd-profile-about">
                <h2>About</h2>
                <p>{candidate.about}</p>
              </section>
            ) : null}

            {photos.length ? (
              <section className="cnd-card cnd-profile-section">
                <div className="cnd-profile-photo-heading">
                  <h2>Photos</h2>
                  {photos.length > 1 ? (
                    <button
                      type="button"
                      className="cnd-profile-slideshow-button"
                      onClick={() => {
                        if (slideshowFullscreen) {
                          closeSlideshow();
                        } else {
                          setSlideshow(true);
                          setSlideshowFullscreen(true);
                        }
                      }}
                    >
                      {slideshow ? <Pause size={16} /> : <Play size={16} />}
                      {slideshow ? "Pause slideshow" : "Play slideshow"}
                    </button>
                  ) : null}

                  {slideshowFullscreen && photos.length ? (
                    <div className="cnd-photo-slideshow" role="dialog" aria-modal="true" aria-label="Photo slideshow">
                      <div className="cnd-photo-slideshow-top">
                        <span>{activePhotoIndex + 1} / {photos.length}</span>
                        <button type="button" onClick={closeSlideshow} aria-label="Close slideshow">
                          <X size={22} />
                        </button>
                      </div>
                      <button
                        type="button"
                        className="cnd-photo-slideshow-arrow cnd-photo-slideshow-prev"
                        onClick={() => movePhoto(-1)}
                        aria-label="Previous photo"
                      >
                        <ChevronLeft size={30} />
                      </button>
                      <img
                        className="cnd-photo-slideshow-image"
                        src={photos[Math.min(activePhotoIndex, photos.length - 1)]}
                        alt={`${candidate.full_name || "Candidate"} photo ${activePhotoIndex + 1}`}
                      />
                      <button
                        type="button"
                        className="cnd-photo-slideshow-arrow cnd-photo-slideshow-next"
                        onClick={() => movePhoto(1)}
                        aria-label="Next photo"
                      >
                        <ChevronRight size={30} />
                      </button>
                      <button
                        type="button"
                        className="cnd-photo-slideshow-control"
                        onClick={() => setSlideshow((current) => !current)}
                      >
                        {slideshow ? <Pause size={18} /> : <Play size={18} />}
                        {slideshow ? "Pause" : "Play"}
                      </button>
                    </div>
                  ) : null}
                </div>
                <div className="cnd-profile-carousel">
                  <img
                    className="cnd-profile-carousel-image"
                    src={photos[Math.min(activePhotoIndex, photos.length - 1)]}
                    alt={`${candidate.full_name || "Candidate"} photo ${activePhotoIndex + 1}`}
                  />
                  {photos.length > 1 ? (
                    <>
                      <button
                        type="button"
                        className="cnd-profile-carousel-arrow cnd-profile-carousel-prev"
                        onClick={() => movePhoto(-1)}
                        aria-label="Previous photo"
                      >
                        <ChevronLeft size={24} />
                      </button>
                      <button
                        type="button"
                        className="cnd-profile-carousel-arrow cnd-profile-carousel-next"
                        onClick={() => movePhoto(1)}
                        aria-label="Next photo"
                      >
                        <ChevronRight size={24} />
                      </button>
                    </>
                  ) : null}
                  <span className="cnd-profile-carousel-count">
                    {activePhotoIndex + 1} / {photos.length}
                  </span>
                </div>
                {photos.length > 1 ? (
                  <div className="cnd-profile-thumbnails">
                    {photos.map((photo, index) => (
                      <button
                        type="button"
                        className={index === activePhotoIndex ? "active" : ""}
                        onClick={() => {
                          setSlideshow(false);
                          setActivePhotoIndex(index);
                        }}
                        aria-label={`Show photo ${index + 1}`}
                        key={`${photo}-${index}`}
                      >
                        <img src={photo} alt="" />
                      </button>
                    ))}
                  </div>
                ) : null}
              </section>
            ) : null}

            <div className="cnd-profile-details-grid">
              {sections.map((section) => {
                const rows = section.rows.filter(([, item]) => value(item) !== null);
                if (!rows.length) return null;
                return (
                  <section className="cnd-card cnd-profile-section" key={section.title}>
                    <h2>{section.title}</h2>
                    <dl>
                      {rows.map(([label, item]) => (
                        <div key={label}><dt>{label}</dt><dd>{displayValue(item)}</dd></div>
                      ))}
                    </dl>
                  </section>
                );
              })}
            </div>
          </>
        )}
      </div>
    </CandidateLayout>
  );
}
