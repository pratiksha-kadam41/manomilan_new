import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import {
  FaAddressBook, FaArrowLeft, FaBriefcase, FaCalendarAlt, FaChevronLeft,
  FaChevronRight, FaDownload, FaEdit, FaFileAlt, FaGraduationCap,
  FaHeartbeat, FaHome, FaIdCard, FaImages, FaInfoCircle, FaListUl, FaMapMarkerAlt,
  FaPause, FaPhoneAlt, FaPlay, FaRedo, FaStar, FaTrash, FaUsers,
} from "react-icons/fa";
import AdminLayout from "../../components/AdminLayout";
import RecordEditorName from "../../components/RecordEditorName";
import { ApiError, apiMediaUrl, download, getProfilePhoto, request } from "../../../api";
import { useAdminPermission } from "../../../permission-context";
import "../../components/admin-ui.css";
import "./ProfileDetail.css";

const NA = "N/A";

const isValidId = (id) => /^\d{1,12}$/.test(String(id ?? "")) && Number(id) > 0;

const safeUrl = (url) => {
  try {
    const parsed = new URL(url, window.location.origin);
    return ["http:", "https:"].includes(parsed.protocol) ? parsed.href : "";
  } catch {
    return "";
  }
};

const normalizeUrl = (value) => {
  if (!value || typeof value !== "string") return "";
  return safeUrl(apiMediaUrl(value));
};

const pick = (obj, path) => String(path).split(".").reduce((o, k) => (o == null ? undefined : o[k]), obj);

const parseDate = (value) => {
  if (!value) return null;
  const text = String(value).trim();
  const match = /^(\d{2})-(\d{2})-(\d{4})$/.exec(text);
  const date = match ? new Date(+match[3], +match[2] - 1, +match[1]) : new Date(text);
  return Number.isNaN(date.getTime()) ? null : date;
};

const ageFrom = (date) => {
  const today = new Date();
  let age = today.getFullYear() - date.getFullYear();
  if (today.getMonth() < date.getMonth() || (today.getMonth() === date.getMonth() && today.getDate() < date.getDate())) age--;
  return age >= 0 && age < 120 ? age : null;
};

const longDate = (date) => `${date.getDate()} ${date.toLocaleString("en-GB", { month: "short" })}, ${date.getFullYear()}`;

const fmt = (value, type) => {
  if (value === null || value === undefined || (typeof value === "object" && !Array.isArray(value))) return null;
  if (Array.isArray(value)) value = value.filter(Boolean).join(", ");
  const text = String(value).trim();
  if (!text) return null;
  if (type === "money") {
    const number = Number(text.replace(/,/g, ""));
    return Number.isFinite(number) ? `₹ ${number.toLocaleString("en-IN")}` : text;
  }
  if (type === "date") {
    const date = parseDate(text);
    return date ? longDate(date) : text;
  }
  return text;
};

const PHOTO_PATH_KEYS = [
  "url",
  "photo_url",
  "profile_photo",
  "profile_photo_url",
  "photo",
  "path",
  "file_path",
  "image_url",
  "src",
];

function collectPhotoEntries(value, labelPrefix, entries) {
  if (typeof value === "string") {
    const text = value.trim();
    if (!text) return;

    if (text.startsWith("[") || text.startsWith("{") || text.startsWith('"')) {
      try {
        const parsed = JSON.parse(text);
        if (parsed !== text) {
          collectPhotoEntries(parsed, labelPrefix, entries);
          return;
        }
      } catch {
        return;
      }
    }

    entries.push({ label: `${labelPrefix} ${entries.length + 1}`, url: text });
    return;
  }

  if (Array.isArray(value)) {
    value.forEach((item) => collectPhotoEntries(item, labelPrefix, entries));
    return;
  }

  if (!value || typeof value !== "object") return;

  const photoKey = PHOTO_PATH_KEYS.find((key) => value[key]);
  if (photoKey && typeof value[photoKey] === "string") {
    entries.push({
      id: value.id,
      label: String(value.label || `${labelPrefix} ${entries.length + 1}`),
      url: value[photoKey],
    });
    return;
  }

  Object.entries(value).forEach(([key, item]) => {
    if (/^\d+$/.test(key) || /photo|image/i.test(key)) {
      collectPhotoEntries(item, labelPrefix, entries);
    }
  });
}

function normalizeProfile(profile) {
  const documents = Array.isArray(profile.documents) ? profile.documents : [];
  const photoEntries = [];
  collectPhotoEntries(profile.profile_photo || profile.photo || profile.photo_url, "Profile Photo", photoEntries);
  collectPhotoEntries(profile.photos, "Photo", photoEntries);
  collectPhotoEntries(profile.full_body_photo_url || profile.full_body_photo, "Full Body Photo", photoEntries);
  collectPhotoEntries(profile.half_body_photo_url || profile.half_body_photo, "Half Body Photo", photoEntries);
  collectPhotoEntries(profile.selfie_photo_url || profile.selfie_photo, "Selfie Photo", photoEntries);
  collectPhotoEntries(profile.additional_photo_urls, "Additional Photo", photoEntries);
  collectPhotoEntries(profile.additional_photos || profile.additionalPhotos, "Additional Photo", photoEntries);
  collectPhotoEntries(profile.candidate_photos || profile.candidatePhotos, "Candidate Photo", photoEntries);
  const seenPhotoUrls = new Set();
  const photos = photoEntries
    .map((photo, index) => ({
      id: photo.id ?? index,
      label: photo.label,
      url: normalizeUrl(photo.url),
    }))
    .filter((photo) => {
      if (!photo.url || seenPhotoUrls.has(photo.url)) return false;
      seenPhotoUrls.add(photo.url);
      return true;
    });

  return {
    ...profile,
    _photo: normalizeUrl(getProfilePhoto(profile, ["profile_photo", "photo", "photo_url"])),
    photos,
    documents: documents
      .filter((document) => document && typeof document === "object")
      .map((document, index) => ({
        id: document.id ?? index,
        label: String(document.label || document.type || document.name || "Document"),
        url: document.url || document.file_path || document.path || "",
      })),
  };
}

async function getProfile(id, signal) {
  if (!isValidId(id)) throw new ApiError("This profile link is not valid.", 400);
  const response = await request(`/profiles/${id}`, { signal });
  const profile = response?.data ?? response;
  if (!profile || typeof profile !== "object" || Array.isArray(profile)) {
    throw new ApiError("The server sent an invalid response.");
  }
  return normalizeProfile(profile);
}

async function deleteDocument(profileId, documentId) {
  if (!isValidId(profileId) || !/^[\w-]{1,64}$/.test(String(documentId))) {
    throw new ApiError("Invalid document.", 400);
  }
  await request(`/profiles/${profileId}/documents/${documentId}`, { method: "DELETE" });
}

async function downloadBiodata(profileId, registrationNo) {
  if (!isValidId(profileId)) throw new ApiError("Invalid profile.", 400);
  const filename = `BioData_${String(registrationNo || profileId).replace(/[^\w-]/g, "")}.pdf`;
  await download(`/profiles/${profileId}/biodata`, filename);
}

const referenceSub = (profile) => {
  const text = [fmt(pick(profile, "reference_name")), fmt(pick(profile, "reference_relation"))].filter(Boolean).join(" - ");
  return text ? `(${text})` : "";
};

const TABS = [
  {
    id: "personal", label: "Personal",
    sections: [
      {
        title: "About", icon: <FaInfoCircle />, fields: [
          { l: "About Me", k: "about", wide: true },
          { l: "Date of Birth", k: "birth_date", t: "date" },
          { l: "Marital Status", k: "marital_status" },
          { l: "Religion", k: "religion" },
          { l: "Caste", k: "caste" },
          { l: "Sub Caste", k: "sub_caste" },
          { l: "Mother Tongue", k: "mother_tongue" },
          { l: "Languages Known", k: "languages_known" },
          { l: "Special Cases", k: "special_cases", wide: true },
        ],
      },
      {
        title: "Contact Details", icon: <FaAddressBook />, fields: [
          { l: "Contact No", k: "mobile" },
          { l: "Email", k: "email" },
          { l: "Current Address", k: "address", wide: true },
          { l: "Permanent Address", k: "permanent_address", wide: true },
          { l: "Father's Contact", k: "father_phone" },
          { l: "Mother's Contact", k: "mother_phone" },
          { l: "Reference Contact", k: "reference_phone", sub: referenceSub },
        ],
      },
      {
        title: "Health & Lifestyle", icon: <FaHeartbeat />, fields: [
          { l: "Blood Group", k: "blood_group" },
          { l: "Spectacles/Lenses", k: "spectacles" },
          { l: "Medical History", k: "personal_medical_history", wide: true },
          { l: "Current Medical Problems", k: "current_medical_problems", wide: true },
          { l: "Medications", k: "medications", wide: true },
        ],
      },
    ],
  },
  {
    id: "professional", label: "Professional",
    sections: [
      {
        title: "Education", icon: <FaGraduationCap />, fields: [
          { l: "Highest Education", k: "education" },
          { l: "University / College", k: "university" },
          { l: "Degree / Diploma 1", k: "degree_diploma_name_1" },
          { l: "Specialisation 1", k: "specialisation_1" },
          { l: "Degree / Diploma 2", k: "degree_diploma_name_2" },
          { l: "Specialisation 2", k: "specialisation_2" },
          { l: "Additional Education 1", k: "additional_education_1" },
          { l: "Additional Education 2", k: "additional_education_2" },
          { l: "City of Education", k: "city_of_education" },
        ],
      },
      {
        title: "Profession", icon: <FaBriefcase />, fields: [
          { l: "Profession", k: "profession" },
          { l: "Designation", k: "designation" },
          { l: "Organisation", k: "organisation" },
          { l: "Field of Working", k: "field_of_working" },
          { l: "Length of Employment", k: "length_of_employment" },
          { l: "Monthly Income", k: "monthly_income", t: "money" },
          { l: "Annual Income", k: "annual_income", t: "money" },
        ],
      },
    ],
  },
  {
    id: "family", label: "Family",
    sections: [
      {
        title: "Family Details", icon: <FaUsers />, fields: [
          { l: "Father's Name", k: "father_name", suffix: "father_living" },
          { l: "Father's Occupation", k: "father_occupation" },
          { l: "Father's Native Place", k: "father_native_place" },
          { l: "Father's Contact", k: "father_phone" },
          { l: "Mother's Name", k: "mother_name", suffix: "mother_living" },
          { l: "Mother's Occupation", k: "mother_occupation" },
          { l: "Mother's Native Place", k: "mother_native_place" },
          { l: "Mother's Contact", k: "mother_phone" },
          { l: "Brothers", k: "brothers" },
          { l: "Brothers Married", k: "brothers_married" },
          { l: "Sisters", k: "sisters" },
          { l: "Sisters Married", k: "sisters_married" },
          { l: "Parents Intercaste Marriage", k: "parents_intercaste" },
          { l: "Parents Different Caste", k: "parents_different_caste" },
          { l: "Family Financial Status", k: "family_financial_status" },
          { l: "Family Annual Income", k: "family_income", t: "money" },
          { l: "Family Values", k: "family_values" },
        ],
      },
    ],
  },
  {
    id: "horoscope", label: "Horoscope",
    sections: [
      {
        title: "Horoscope Details", icon: <FaStar />, fields: [
          { l: "Date of Birth", k: "horoscope_dob", t: "date" },
          { l: "Time of Birth", k: "birth_time" },
          { l: "Place of Birth", k: "birth_place" },
          { l: "Horoscope Caste", k: "horoscope_caste" },
          { l: "Horoscope Sub Caste", k: "horoscope_sub_caste" },
          { l: "Horoscope", k: "horoscope" },
          { l: "Horoscope Matching", k: "horoscope_match_required" },
          { l: "Manglik Status", k: "manglik_status" },
          { l: "Rashi", k: "rashi" },
          { l: "Nakshatra", k: "nakshatra" },
          { l: "Charan", k: "charan" },
          { l: "Nadi", k: "nadi" },
          { l: "Gan", k: "gan" },
          { l: "Gotra", k: "gotra" },
        ],
      },
    ],
  },
  { id: "photos", label: "Photos" },
  { id: "documents", label: "Documents" },
  {
    id: "preference", label: "Preference",
    sections: [
      {
        title: "Partner Preferences", icon: <FaListUl />, fields: [
          { l: "Age Range", k: "pref_age_range" },
          { l: "Height Range", k: "pref_height_range" },
          { l: "Preferred Religion", k: "pref_religion" },
          { l: "Preferred Caste", k: "pref_caste" },
          { l: "Preferred Sub-Caste", k: "pref_sub_caste" },
          { l: "Preferred Education", k: "pref_education" },
          { l: "Working Partner", k: "pref_working_partner" },
          { l: "Preferred Working Field", k: "pref_working_field" },
          { l: "Expected Income", k: "pref_income" },
          { l: "Family Type", k: "pref_family_type" },
          { l: "Financial Background", k: "pref_financial_background" },
          { l: "Family Values", k: "pref_family_values" },
          { l: "Preferred Country", k: "pref_country" },
          { l: "Preferred State", k: "pref_state" },
          { l: "Preferred City", k: "pref_city" },
          { l: "Other Expectations", k: "other_expectations", wide: true },
        ],
      },
    ],
  },
  { id: "melava", label: "Melava", sections: [] },
];

function Field({ field, profile }) {
  const value = fmt(pick(profile, field.k), field.t);
  const suffix = field.suffix ? fmt(pick(profile, field.suffix)) : null;
  const sub = value && field.sub ? field.sub(profile) : "";
  return (
    <div className={`pd-field${field.wide ? " pd-wide" : ""}`}>
      <span className="pd-label">{field.l}</span>
      <span className={`pd-value${value ? "" : " pd-empty"}`}>
        {value ? <>{value}{suffix && <em> ({suffix})</em>}</> : NA}
      </span>
      {sub && <small className="pd-sub">{sub}</small>}
    </div>
  );
}

function Section({ section, profile }) {
  return (
    <section className="pd-section">
      <h3 className="pd-section-title"><span className="pd-section-ic">{section.icon}</span>{section.title}</h3>
      {section.kind === "text" ? (
        <p className="pd-text">{fmt(pick(profile, section.k)) || section.empty}</p>
      ) : (
        <div className="pd-grid">
          {section.fields.map((field) => <Field key={field.k + field.l} field={field} profile={profile} />)}
        </div>
      )}
    </section>
  );
}

function RecordList({ title, records, fields }) {
  if (!Array.isArray(records) || records.length === 0) return null;
  return (
    <section className="pd-section">
      <h3 className="pd-section-title"><span className="pd-section-ic"><FaListUl /></span>{title}</h3>
      {records.map((record, index) => (
        <div className="pd-record" key={`${title}-${index}`}>
          <strong>{title} {index + 1}</strong>
          <RecordEditorName record={record} />
          <div className="pd-grid">
            {fields.map(([label, key]) => (
              <div className="pd-field" key={key}>
                <span className="pd-label">{label}</span>
                <span className={`pd-value${record[key] ? "" : " pd-empty"}`}>{fmt(record[key]) || NA}</span>
              </div>
            ))}
          </div>
        </div>
      ))}
    </section>
  );
}

function PhotoGallery({ photos, name }) {
  const [index, setIndex] = useState(0);
  const [playing, setPlaying] = useState(false);
  const count = photos.length;

  useEffect(() => {
    if (!playing || count < 2) return undefined;
    const timer = window.setInterval(() => {
      setIndex((current) => (current + 1) % count);
    }, 4000);
    return () => window.clearInterval(timer);
  }, [playing, count]);

  if (!count) {
    return (
      <section className="pd-gallery ui-card">
        <div className="pd-gallery-empty">
          <FaImages />
          <strong>No photos uploaded</strong>
          <p>This candidate has not uploaded any photos yet.</p>
        </div>
      </section>
    );
  }

  const safeIndex = Math.min(index, count - 1);
  const current = photos[safeIndex];
  const move = (step) => setIndex((currentIndex) => (currentIndex + step + count) % count);

  return (
    <section className="pd-gallery ui-card" aria-label="Candidate photo slideshow">
      <div className="pd-stage">
        <img src={current.url} alt={current.label || name} />
        {count > 1 && (
          <>
            <button type="button" className="pd-nav prev" onClick={() => move(-1)} aria-label="Previous photo">
              <FaChevronLeft />
            </button>
            <button type="button" className="pd-nav next" onClick={() => move(1)} aria-label="Next photo">
              <FaChevronRight />
            </button>
          </>
        )}
        <span className="pd-counter">{safeIndex + 1} / {count}</span>
        <span className="pd-stage-label">{current.label}</span>
      </div>
      <div className="pd-gallery-bar">
        <div className="pd-thumbs" role="tablist" aria-label="All candidate photos">
          {photos.map((photo, photoIndex) => (
            <button
              type="button"
              key={photo.id ?? photoIndex}
              className={`pd-thumb${photoIndex === safeIndex ? " active" : ""}`}
              onClick={() => setIndex(photoIndex)}
              aria-label={`Show ${photo.label}`}
              aria-pressed={photoIndex === safeIndex}
            >
              <img src={photo.url} alt="" loading="lazy" />
            </button>
          ))}
        </div>
        {count > 1 && (
          <button type="button" className="ui-btn small secondary pd-play" onClick={() => setPlaying((value) => !value)}>
            {playing ? <FaPause /> : <FaPlay />} {playing ? "Pause" : "Slideshow"}
          </button>
        )}
      </div>
    </section>
  );
}

function PhotoGrid({ photos }) {
  return (
    <section className="pd-section">
      <h3 className="pd-section-title"><span className="pd-section-ic"><FaImages /></span>All Photos Uploaded by Candidate</h3>
      {photos.length === 0 ? (
        <p className="pd-text">No photos have been uploaded for this profile.</p>
      ) : (
        <div className="pd-photos">
          {photos.map((photo) => (
            <a key={photo.id} className="pd-photo-card" href={photo.url} target="_blank" rel="noopener noreferrer">
              <img src={photo.url} alt={photo.label} loading="lazy" />
              <span>{photo.label}</span>
            </a>
          ))}
        </div>
      )}
    </section>
  );
}

function Documents({ docs, onDelete, deletingId, canDelete }) {
  return (
    <section className="pd-section">
      <h3 className="pd-section-title"><span className="pd-section-ic"><FaFileAlt /></span>Uploaded Documents</h3>
      {docs.length === 0 ? (
        <p className="pd-text">No documents have been uploaded yet.</p>
      ) : (
        <ul className="pd-docs">
          {docs.map((document) => {
            const url = normalizeUrl(document.url);
            return (
              <li key={document.id}>
                <span className="pd-doc-ic">{/birth/i.test(document.label) ? <FaCalendarAlt /> : <FaIdCard />}</span>
                {url ? <a href={url} target="_blank" rel="noopener noreferrer">{document.label}</a> : <span>{document.label}</span>}
                {canDelete ? (
                  <button
                    type="button"
                    className="pd-icon-btn"
                    aria-label={`Delete ${document.label}`}
                    disabled={deletingId === document.id}
                    onClick={() => onDelete(document)}
                  >
                    <FaTrash />
                  </button>
                ) : null}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

function SummaryCard({ profile }) {
  const name = fmt(profile.full_name) || "Unnamed profile";
  const initials = name.split(/\s+/).slice(0, 2).map((word) => word[0]).join("").toUpperCase();
  const dob = parseDate(profile.dob || profile.birth_date);
  const age = dob ? ageFrom(dob) : profile.age;
  const weight = fmt(profile.weight);
  const completion = Number(profile.profile_completion);
  const verified = String(profile.verification_status || "").toLowerCase() === "completed";

  const facts = [
    ["Gender", fmt(profile.gender)],
    ["Date of Birth", dob ? longDate(dob) : fmt(profile.dob || profile.birth_date, "date")],
    ["Age", age ? `${age} years` : null],
    ["Height", fmt(profile.height)],
    ["Weight", weight ? (/^\d+(\.\d+)?$/.test(weight) ? `${weight} kg` : weight) : null],
    ["Profession", fmt(profile.profession)],
    ["City", fmt(profile.city)],
    ["Mobile", fmt(profile.mobile)],
    ["Email", fmt(profile.email)],
    ["Registration Type", fmt(profile.registration_type)],
    ["Melava Batch", fmt(profile.melava_batch)],
    ["Profile Completion", Number.isFinite(completion) ? `${completion}%` : fmt(profile.profile_completion)],
  ].filter(([, value]) => value);

  return (
    <aside className="pd-summary ui-card">
      <div className="pd-summary-head">
        <span className="pd-summary-avatar">
          {profile._photo ? <img src={profile._photo} alt={name} /> : initials}
        </span>
        <div>
          <h1>{name}</h1>
          <p>{fmt(profile.registration_no) || fmt(profile.registration_number) || "No registration number"}</p>
        </div>
      </div>

      <div className="pd-badges">
        <span className={`ui-badge ${verified ? "ok" : "wait"}`}>{verified ? "Verified" : "Pending verification"}</span>
        {fmt(profile.active) && <span className={`ui-badge ${String(profile.active).toLowerCase() === "active" ? "info" : "off"}`}>{profile.active}</span>}
        {fmt(profile.serial_no) && <span className="ui-badge neutral">Sr. No. {profile.serial_no}</span>}
      </div>

      <dl className="pd-facts-grid">
        {facts.map(([label, value]) => (
          <div className="pd-fact" key={label}>
            <dt>{label}</dt>
            <dd>{value}</dd>
          </div>
        ))}
      </dl>

      <div className="pd-mini-facts">
        {fmt(profile.address) && (
          <span><FaHome /> {profile.address}</span>
        )}
        {fmt(profile.mobile) && (
          <span><FaPhoneAlt /> {profile.mobile}</span>
        )}
        {fmt(profile.city) && (
          <span><FaMapMarkerAlt /> {profile.city}</span>
        )}
      </div>
      <RecordEditorName record={profile} />
    </aside>
  );
}

function Skeleton() {
  return (
    <div className="pd-skeleton" aria-busy="true" aria-label="Loading profile">
      <div className="pd-skeleton-top">
        <div className="pd-sk pd-shimmer" />
        <div className="pd-sk pd-shimmer" />
      </div>
      <div className="pd-skeleton-grid">
        <div className="pd-sk pd-sk-gallery pd-shimmer" />
        <div className="pd-sk pd-sk-summary pd-shimmer" />
      </div>
      <div className="pd-sk pd-sk-panel pd-shimmer" />
    </div>
  );
}

function StateCard({ title, message, onRetry }) {
  return (
    <div className="pd-state" role="alert">
      <h2>{title}</h2>
      <p>{message}</p>
      <div>
        {onRetry && <button type="button" className="ui-btn" onClick={onRetry}><FaRedo /> Try again</button>}
        <Link to="/admin/profiles" className="ui-btn secondary"><FaArrowLeft /> Back to Profiles</Link>
      </div>
    </div>
  );
}

export default function ProfileDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const idOk = isValidId(id);
  const canEditProfile = useAdminPermission("edit", "/admin/profiles");
  const canExportProfile = useAdminPermission("export", "/admin/profiles");
  const canDeleteDocument = useAdminPermission("delete", "/admin/profiles");

  const [state, setState] = useState({ status: "loading", profile: null, error: "", notFound: false });
  const [tab, setTab] = useState("personal");
  const [notice, setNotice] = useState(null);
  const [downloading, setDownloading] = useState(false);
  const [deletingId, setDeletingId] = useState(null);

  const load = async (signal) => {
    try {
      const profile = await getProfile(id, signal);
      setState({ status: "ready", profile, error: "", notFound: false });
    } catch (error) {
      if (error.name === "AbortError") return;
      setState({
        status: "error",
        profile: null,
        error: error.message || "Something went wrong.",
        notFound: error.status === 404,
      });
    }
  };

  const retry = () => {
    setState({ status: "loading", profile: null, error: "", notFound: false });
    load();
  };

  useEffect(() => {
    if (!idOk) return undefined;
    const controller = new AbortController();
    let ignore = false;
    getProfile(id, controller.signal)
      .then((profile) => {
        if (!ignore) setState({ status: "ready", profile, error: "", notFound: false });
      })
      .catch((error) => {
        if (ignore || error.name === "AbortError") return;
        setState({
          status: "error",
          profile: null,
          error: error.message || "Something went wrong.",
          notFound: error.status === 404,
        });
      });
    return () => {
      ignore = true;
      controller.abort();
    };
  }, [idOk, id]);

  useEffect(() => {
    if (notice?.type !== "success") return undefined;
    const timer = window.setTimeout(() => setNotice(null), 4000);
    return () => window.clearTimeout(timer);
  }, [notice]);

  const profile = state.profile;

  const handleDownload = async () => {
    setDownloading(true);
    setNotice(null);
    try {
      await downloadBiodata(id, profile?.registration_no);
      setNotice({ type: "success", text: "BioData downloaded." });
    } catch (error) {
      setNotice({ type: "error", text: error.message || "Could not download the BioData." });
    } finally {
      setDownloading(false);
    }
  };

  const handleDeleteDoc = async (document) => {
    if (!window.confirm(`Delete "${document.label}"? This cannot be undone.`)) return;
    setDeletingId(document.id);
    setNotice(null);
    try {
      await deleteDocument(id, document.id);
      setState((current) => ({
        ...current,
        profile: {
          ...current.profile,
          documents: current.profile.documents.filter((item) => item.id !== document.id),
        },
      }));
      setNotice({ type: "success", text: `${document.label} deleted.` });
    } catch (error) {
      setNotice({ type: "error", text: error.message || "Could not delete the document." });
    } finally {
      setDeletingId(null);
    }
  };

  let body;
  if (!idOk) {
    body = <StateCard title="Invalid profile link" message="The profile id in this link is not valid. Open the profile from the Profiles list." />;
  } else if (state.status === "loading") {
    body = <Skeleton />;
  } else if (state.status === "error") {
    body = (
      <StateCard
        title={state.notFound ? "Profile not found" : "Could not load this profile"}
        message={state.error}
        onRetry={state.notFound ? undefined : retry}
      />
    );
  } else {
    const active = TABS.find((item) => item.id === tab) || TABS[0];
    const name = fmt(profile.full_name) || "Candidate profile";
    body = (
      <div className="pd-page">
        <div className="pd-top">
          <div>
            <Link to="/admin/profiles" className="pd-back"><FaArrowLeft /> Back to Profiles</Link>
            <h1 className="pd-title">{name}</h1>
            <p className="pd-subtitle">Candidate information, uploaded photos, and verification details.</p>
          </div>
          <div className="pd-top-actions">
            {canEditProfile ? (
              <button type="button" className="ui-btn secondary" onClick={() => navigate(`/admin/profiles/edit/${id}`)}>
                <FaEdit /> Edit Profile
              </button>
            ) : null}
            {canExportProfile ? (
              <button type="button" className="ui-btn" onClick={handleDownload} disabled={downloading}>
                <FaDownload /> {downloading ? "Preparing…" : "BioData Download"}
              </button>
            ) : null}
          </div>
        </div>

        <div className="pd-hero">
          <PhotoGallery key={profile.id} photos={profile.photos} name={name} />
          <SummaryCard profile={profile} />
        </div>

        <div className="ui-card pd-tabs-card">
          <div className="pd-tabs" role="tablist" aria-label="Profile sections">
            {TABS.map((item) => (
              <button
                key={item.id}
                type="button"
                role="tab"
                id={`pd-tab-${item.id}`}
                aria-selected={tab === item.id}
                aria-controls="pd-panel"
                className={`pd-tab${tab === item.id ? " active" : ""}`}
                onClick={() => setTab(item.id)}
              >
                {item.label}
              </button>
            ))}
          </div>
          <div id="pd-panel" role="tabpanel" aria-labelledby={`pd-tab-${active.id}`} className="pd-panel">
            {active.id === "photos"
              ? <PhotoGrid photos={profile.photos} />
              : active.id === "documents"
                ? <Documents docs={profile.documents} onDelete={handleDeleteDoc} deletingId={deletingId} canDelete={canDeleteDocument} />
                : active.id === "melava"
                  ? <RecordList
                      title="Melava Registration"
                      records={profile.melava_registrations}
                      fields={[
                        ["Campaign", "campaign_title"],
                        ["Campaign ID", "melava_campaign_id"],
                        ["Booklet Requested", "is_booklet_req"],
                        ["Payment Verified", "payment_verified"],
                        ["Payment Screenshot", "payment_screenshot"],
                      ]}
                    />
                : active.sections.map((section) => <Section key={section.title} section={section} profile={profile} />)}
            {active.id === "professional" && (
              <>
                <RecordList
                  title="Education Record"
                  records={profile.education_records}
                  fields={[
                    ["Level", "education_level"],
                    ["Degree", "degree_name"],
                    ["Specialisation", "specialization"],
                    ["University", "university_name"],
                    ["College", "college_name"],
                    ["Passing Year", "passing_year"],
                    ["Percentage", "percentage"],
                    ["Grade", "grade"],
                    ["City", "education_city"],
                    ["Additional Qualification", "additional_qualification"],
                  ]}
                />
                <RecordList
                  title="Employment Record"
                  records={profile.employment_records}
                  fields={[
                    ["Type", "employment_type"],
                    ["Profession", "profession"],
                    ["Working Field", "working_field"],
                    ["Organisation", "organisation_name"],
                    ["Designation", "designation"],
                    ["City", "employment_city"],
                    ["Duration", "employment_duration"],
                    ["Monthly Income", "monthly_income"],
                    ["Annual Income", "annual_income"],
                    ["Currency", "income_currency"],
                  ]}
                />
              </>
            )}
          </div>
        </div>
      </div>
    );
  }

  return (
    <AdminLayout currentPath="/admin/profiles">
      <div className="adm-ui pd-shell">
        {notice && (
          <div className={`pd-alert pd-alert-${notice.type}`} role="alert">
            <span>{notice.text}</span>
            <button type="button" aria-label="Dismiss message" onClick={() => setNotice(null)}>×</button>
          </div>
        )}
        {body}
      </div>
    </AdminLayout>
  );
}
