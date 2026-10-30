import { useEffect, useMemo, useRef, useState } from "react";
import {
  BadgeCheck, Briefcase, Camera, CheckCircle2, FileText, GraduationCap,
  Eye, Heart, Loader2, MapPin, Moon, Save, Settings2, ShieldCheck, Sparkles,
  Trash2, Upload, UserRound, Users,
} from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import {
  Avatar, ConfirmDialog, DashboardSkeleton, ErrorState, FormField,
  InlineNote, Modal, PageHeader, ProgressBar, ProgressRing, SectionCard,
  Toasts,
} from "../components/Ui";
import { useToasts } from "../useToasts";
import { getCachedHomeData } from "../../auth";
import {
  createCandidateProfile, getCandidateProfile, removeCandidateDocument,
  removeCandidatePhoto, savePreferences, updateCandidateProfile,
  uploadCandidateDocuments, uploadCandidatePhoto,
} from "../api";
import {
  displayValue, fileSizeLabel, formatDate, humanGender, text,
} from "../utils";

const TABS = [
  ["overview", "Overview", UserRound],
  ["edit", "Edit profile", Settings2],
  ["preferences", "Preferences", Heart],
  ["documents", "Documents", FileText],
];

const EDIT_SECTIONS = [
  {
    key: "basic",
    title: "Basic details",
    subtitle: "Your identity and personal information",
    icon: UserRound,
    fields: [
      { name: "full_name", label: "Full name", required: true, placeholder: "Full name as per documents" },
      { name: "gender", label: "Profile type", type: "select", required: true, options: [["groom", "Groom"], ["bride", "Bride"]] },
      { name: "birth_date", label: "Date of birth", type: "date", required: true },
      { name: "email", label: "Email address", type: "email", placeholder: "you@example.com" },
      { name: "religion", label: "Religion", placeholder: "Religion" },
      { name: "caste", label: "Community", placeholder: "Community" },
      { name: "sub_caste", label: "Sub community", placeholder: "Sub community" },
      { name: "marital_status", label: "Marital status", placeholder: "Never married, divorced..." },
      { name: "height", label: "Height", placeholder: "5 ft 6 in" },
      { name: "weight", label: "Weight", placeholder: "60 kg" },
      { name: "blood_group", label: "Blood group", placeholder: "B+" },
      { name: "mother_tongue", label: "Mother tongue", placeholder: "Marathi" },
      { name: "language", label: "Languages known", placeholder: "Marathi, Hindi, English" },
      { name: "about", label: "About you", type: "textarea", rows: 5, full: true, placeholder: "Describe yourself, your values, interests and what you are looking for." },
      { name: "address", label: "Current address", type: "textarea", rows: 3, full: true, placeholder: "Current residential address" },
      { name: "permanent_address", label: "Permanent address", type: "textarea", rows: 3, full: true, placeholder: "Permanent address" },
    ],
  },
  {
    key: "education",
    title: "Education and career",
    subtitle: "Your qualifications and professional journey",
    icon: GraduationCap,
    fields: [
      { name: "education", label: "Highest education", placeholder: "Post graduate" },
      { name: "degree", label: "Degree / diploma", placeholder: "Degree name" },
      { name: "degree_2", label: "Additional degree", placeholder: "Second degree" },
      { name: "specialisation", label: "Specialisation", placeholder: "Specialisation" },
      { name: "college", label: "College / university", placeholder: "Institute name" },
      { name: "city_of_education", label: "City of education", placeholder: "City" },
      { name: "additional_education", label: "Additional education", placeholder: "Certifications or courses" },
      { name: "profession", label: "Profession", placeholder: "Software engineer" },
      { name: "field_of_working", label: "Working field", placeholder: "Information technology" },
      { name: "organisation", label: "Organisation", placeholder: "Company name" },
      { name: "designation", label: "Designation", placeholder: "Senior engineer" },
      { name: "professional_city", label: "Work city", placeholder: "Pune" },
      { name: "length_of_employment", label: "Experience", placeholder: "5 years" },
      { name: "monthly_income", label: "Monthly income", placeholder: "Optional" },
      { name: "annual_income", label: "Annual income", placeholder: "Optional" },
    ],
  },
  {
    key: "lifestyle",
    title: "Lifestyle and interests",
    subtitle: "What life looks like beyond work",
    icon: Sparkles,
    fields: [
      { name: "hobbies", label: "Hobbies", type: "textarea", rows: 2, full: true, placeholder: "Reading, travelling, music..." },
      { name: "extra_curricular", label: "Extra-curricular", type: "textarea", rows: 2, full: true, placeholder: "Activities you enjoy" },
      { name: "achievements", label: "Achievements", type: "textarea", rows: 2, full: true, placeholder: "Notable achievements" },
      { name: "sports", label: "Sports and fitness", placeholder: "Cricket, gym, yoga..." },
      { name: "cooking_skills", label: "Cooking skills", placeholder: "Optional" },
      { name: "diet", label: "Diet", placeholder: "Vegetarian / non-vegetarian" },
      { name: "smoking", label: "Smoking", type: "select", options: [["", "Select"], ["No", "No"], ["Occasionally", "Occasionally"], ["Yes", "Yes"]] },
      { name: "drinking", label: "Drinking", type: "select", options: [["", "Select"], ["No", "No"], ["Occasionally", "Occasionally"], ["Yes", "Yes"]] },
      { name: "partying", label: "Partying", placeholder: "Optional" },
    ],
  },
  {
    key: "family",
    title: "Family details",
    subtitle: "Tell candidates about your family",
    icon: Users,
    fields: [
      { name: "father_name", label: "Father's name", placeholder: "Father's name" },
      { name: "father_alive", label: "Father alive", type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "father_occupation", label: "Father's occupation", placeholder: "Occupation" },
      { name: "father_contact", label: "Father's contact", placeholder: "Optional" },
      { name: "mother_name", label: "Mother's name", placeholder: "Mother's name" },
      { name: "mother_alive", label: "Mother alive", type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "mother_occupation", label: "Mother's occupation", placeholder: "Occupation" },
      { name: "mother_contact", label: "Mother's contact", placeholder: "Optional" },
      { name: "brothers", label: "Number of brothers", type: "number", min: 0, max: 20 },
      { name: "brothers_married", label: "Brothers married", type: "number", min: 0, max: 20 },
      { name: "sisters", label: "Number of sisters", type: "number", min: 0, max: 20 },
      { name: "sisters_married", label: "Sisters married", type: "number", min: 0, max: 20 },
      { name: "family_values", label: "Family values", placeholder: "Traditional, moderate..." },
      { name: "family_financial_status", label: "Financial status", placeholder: "Middle class" },
      { name: "family_annual_income", label: "Family annual income", placeholder: "Optional" },
    ],
  },
  {
    key: "other",
    title: "Astrology and additional details",
    subtitle: "Optional information for a complete profile",
    icon: Moon,
    fields: [
      { name: "place_of_birth", label: "Place of birth", placeholder: "City" },
      { name: "time_of_birth", label: "Time of birth", type: "time" },
      { name: "birth_time_ampm", label: "AM / PM", type: "select", options: [["", "Select"], ["AM", "AM"], ["PM", "PM"]] },
      { name: "rashi", label: "Rashi", placeholder: "Optional" },
      { name: "nakshatra", label: "Nakshatra", placeholder: "Optional" },
      { name: "gotra", label: "Gotra", placeholder: "Optional" },
      { name: "nadi", label: "Nadi", placeholder: "Optional" },
      { name: "home_status", label: "Home status", placeholder: "Own house / rented" },
      { name: "has_vehicle", label: "Owns a vehicle", type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "vehicle_type", label: "Vehicle type", placeholder: "Car, bike..." },
      { name: "instagram", label: "Instagram", placeholder: "Optional" },
      { name: "facebook", label: "Facebook", placeholder: "Optional" },
    ],
  },
];

const ALL_EDIT_FIELDS = EDIT_SECTIONS.flatMap((section) => section.fields);

const PREFERENCE_SECTIONS = [
  {
    key: "partner",
    title: "Partner basics",
    subtitle: "Age, community and education",
    icon: Heart,
    fields: [
      { name: "preferred_religion", label: "Preferred religion", placeholder: "Any religion" },
      { name: "preferred_caste", label: "Preferred community", placeholder: "Any community" },
      { name: "preferred_sub_caste", label: "Preferred sub community", placeholder: "Optional" },
      { name: "preferred_education", label: "Preferred education", placeholder: "Any education" },
      { name: "age_from", label: "Age from", type: "number", min: 18, max: 100 },
      { name: "age_to", label: "Age to", type: "number", min: 18, max: 100 },
      { name: "height_from", label: "Height from", placeholder: "5 ft" },
      { name: "height_to", label: "Height to", placeholder: "6 ft" },
    ],
  },
  {
    key: "work",
    title: "Work and lifestyle",
    subtitle: "Professional and family expectations",
    icon: Briefcase,
    fields: [
      { name: "working_partner", label: "Working partner", type: "select", options: [["", "No preference"], ["Yes", "Yes"], ["No", "No"], ["Doesn't matter", "Doesn't matter"]] },
      { name: "preferred_working_field", label: "Preferred working field", placeholder: "Any field" },
      { name: "expected_income", label: "Expected income", placeholder: "Optional" },
      { name: "family_type", label: "Family type", placeholder: "Joint / nuclear" },
      { name: "financial_background", label: "Financial background", placeholder: "Optional" },
      { name: "family_values", label: "Family values", placeholder: "Traditional / moderate" },
    ],
  },
  {
    key: "location",
    title: "Location and expectations",
    subtitle: "Where you would like your partner to be",
    icon: MapPin,
    fields: [
      { name: "preferred_location", label: "Preferred location", placeholder: "City or region" },
      { name: "preferred_country", label: "Preferred country", placeholder: "India" },
      { name: "preferred_state", label: "Preferred state", placeholder: "Maharashtra" },
      { name: "preferred_city", label: "Preferred city", placeholder: "Pune" },
      { name: "other_expectations", label: "Other expectations", type: "textarea", rows: 4, full: true, placeholder: "Describe any other qualities you are looking for." },
    ],
  },
];

const ALL_PREFERENCE_FIELDS = PREFERENCE_SECTIONS.flatMap((section) => section.fields);

const DOCUMENT_LABELS = [
  ["identificationProof", "Identity proof", "Aadhaar, PAN or passport"],
  ["birthDateProof", "Birth date proof", "Birth certificate or school certificate"],
  ["addressProof", "Address proof", "Utility bill, rent agreement or licence"],
  ["educationProof", "Education proof", "Degree or marksheet"],
  ["professionProof", "Profession proof", "Employment or professional certificate"],
  ["incomeProof", "Income proof", "Salary slip or income certificate"],
  ["casteCertificate", "Caste certificate", "Optional caste certificate"],
  ["medicalFitnessCertificate", "Medical fitness", "Optional medical certificate"],
];

const OVERVIEW_GROUPS = [
  {
    title: "Personal",
    icon: UserRound,
    rows: [
      ["Full name", "full_name"],
      ["Age", "ageText"],
      ["Profile type", "genderText"],
      ["Marital status", "marital_status"],
      ["Height", "height"],
      ["Blood group", "blood_group"],
      ["Mother tongue", "mother_tongue"],
      ["Location", "city"],
      ["Religion", "religion"],
      ["Community", "caste"],
    ],
  },
  {
    title: "Education and career",
    icon: GraduationCap,
    rows: [
      ["Education", "education"],
      ["Degree", "degree"],
      ["College", "college"],
      ["Profession", "profession"],
      ["Working field", "field_of_working"],
      ["Organisation", "organisation"],
      ["Designation", "designation"],
      ["Annual income", "annual_income"],
    ],
  },
  {
    title: "Family",
    icon: Users,
    rows: [
      ["Father", "father_name"],
      ["Mother", "mother_name"],
      ["Brothers", "brothers"],
      ["Sisters", "sisters"],
      ["Family values", "family_values"],
      ["Financial status", "family_financial_status"],
    ],
  },
  {
    title: "Lifestyle",
    icon: Sparkles,
    rows: [
      ["Diet", "diet"],
      ["Smoking", "smoking"],
      ["Drinking", "drinking"],
      ["Hobbies", "hobbies"],
      ["Sports", "sports"],
      ["Languages", "language"],
    ],
  },
];

const dateValue = (value) => {
  if (!value) return "";
  return String(value).slice(0, 10);
};

const timeValue = (value) => {
  if (!value) return "";
  return String(value).slice(0, 5);
};

const buildForm = (profile) => {
  const details = profile?.details || {};
  const form = {
    full_name: profile?.full_name || "",
    gender: profile?.gender || "",
    birth_date: dateValue(profile?.birth_date),
    email: profile?.email || "",
  };
  ALL_EDIT_FIELDS.forEach(({ name }) => {
    if (!(name in form)) form[name] = details[name] ?? "";
  });
  form.time_of_birth = timeValue(details.time_of_birth);
  return form;
};

const buildPreferenceForm = (preference) => {
  const form = {};
  ALL_PREFERENCE_FIELDS.forEach(({ name }) => {
    form[name] = preference?.[name] ?? "";
  });
  return form;
};

const tabFromUrl = () => {
  if (typeof window === "undefined") return "overview";
  const value = new URLSearchParams(window.location.search).get("tab");
  return TABS.some(([key]) => key === value) ? value : "overview";
};

export default function CandidateProfile() {
  const cached = useMemo(() => getCachedHomeData(), []);
  const [profile, setProfile] = useState(null);
  const [account, setAccount] = useState(cached?.user || null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [tab, setTab] = useState(tabFromUrl);
  const [form, setForm] = useState(() => buildForm(null));
  const [preferenceForm, setPreferenceForm] = useState(() => buildPreferenceForm(null));
  const [fieldErrors, setFieldErrors] = useState({});
  const [preferenceErrors, setPreferenceErrors] = useState({});
  const [formNotice, setFormNotice] = useState("");
  const [preferenceNotice, setPreferenceNotice] = useState("");
  const [saving, setSaving] = useState(false);
  const [savingPreference, setSavingPreference] = useState(false);
  const [photoBusy, setPhotoBusy] = useState(false);
  const [photoError, setPhotoError] = useState("");
  const [removePhotoOpen, setRemovePhotoOpen] = useState(false);
  const [docFiles, setDocFiles] = useState({});
  const [docBusy, setDocBusy] = useState(false);
  const [docError, setDocError] = useState("");
  const [removeDoc, setRemoveDoc] = useState(null);
  const [removeDocBusy, setRemoveDocBusy] = useState(false);
  const [viewDocument, setViewDocument] = useState(null);
  const photoInput = useRef(null);
  const { toasts, push, dismiss } = useToasts();

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      const result = await getCandidateProfile();
      setProfile(result?.profile || null);
      setAccount(result?.user || cached?.user || null);
      setForm(buildForm(result?.profile));
      setPreferenceForm(buildPreferenceForm(result?.profile?.preference));
    } catch (loadError) {
      setError(loadError.message || "Could not load your profile.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    Promise.resolve().then(load);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const candidateInfo = {
    name: profile?.full_name || account?.name || account?.username,
    id: profile?.registration_number || profile?.profile_id || account?.username,
    photo: profile?.photo_url || "",
  };

  const completion = Number(profile?.profile_completion || 0);
  const documents = profile?.documents || {};
  const uploadedDocuments = DOCUMENT_LABELS.filter(([key]) => documents[key]?.file_url);

  const updateField = (name, value) => {
    setForm((current) => ({ ...current, [name]: value }));
    setFieldErrors((current) => ({ ...current, [name]: undefined }));
  };

  const updatePreference = (name, value) => {
    setPreferenceForm((current) => ({ ...current, [name]: value }));
    setPreferenceErrors((current) => ({ ...current, [name]: undefined }));
  };

  const saveProfile = async (event) => {
    event.preventDefault();
    setSaving(true);
    setFormNotice("");
    setFieldErrors({});
    try {
      const payload = profile ? updateCandidateProfile(form) : createCandidateProfile({
        full_name: form.full_name,
        gender: form.gender,
        birth_date: form.birth_date,
        email: form.email,
      });
      const result = await payload;
      setProfile(result?.profile || null);
      setAccount(result?.user || account);
      setForm(buildForm(result?.profile));
      setPreferenceForm(buildPreferenceForm(result?.profile?.preference));
      push(profile ? "Your profile has been updated." : "Your candidate profile has been created.");
      if (!profile) setTab("overview");
    } catch (saveError) {
      setFieldErrors(saveError.errors || {});
      setFormNotice(saveError.message || "Could not save your profile.");
    } finally {
      setSaving(false);
    }
  };

  const savePartnerPreferences = async (event) => {
    event.preventDefault();
    setSavingPreference(true);
    setPreferenceNotice("");
    setPreferenceErrors({});
    try {
      const result = await savePreferences(preferenceForm);
      setProfile((current) => current ? { ...current, preference: result?.preference, has_preferences: true } : current);
      setPreferenceForm(buildPreferenceForm(result?.preference));
      push("Partner preferences saved. Your matches will now be ranked accordingly.");
    } catch (saveError) {
      setPreferenceErrors(saveError.errors || {});
      setPreferenceNotice(saveError.message || "Could not save your preferences.");
    } finally {
      setSavingPreference(false);
    }
  };

  const handlePhoto = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    setPhotoError("");
    if (!file.type.startsWith("image/")) {
      setPhotoError("Choose a JPG, PNG or WebP image.");
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setPhotoError("The photo must be 5 MB or smaller.");
      return;
    }
    setPhotoBusy(true);
    try {
      const result = await uploadCandidatePhoto(file);
      setProfile(result?.profile || profile);
      push("Profile photo updated.");
    } catch (uploadError) {
      setPhotoError(uploadError.message || "Could not upload the photo.");
    } finally {
      setPhotoBusy(false);
    }
  };

  const deletePhoto = async () => {
    setPhotoBusy(true);
    try {
      const result = await removeCandidatePhoto();
      setProfile(result?.profile || profile);
      setRemovePhotoOpen(false);
      push("Profile photo removed.");
    } catch (removeError) {
      push(removeError.message || "Could not remove the photo.", "error");
    } finally {
      setPhotoBusy(false);
    }
  };

  const chooseDocument = (key, event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setDocFiles((current) => ({ ...current, [key]: file }));
    setDocError("");
  };

  const uploadDocuments = async () => {
    const selected = Object.fromEntries(Object.entries(docFiles).filter(([, file]) => file));
    if (!Object.keys(selected).length) {
      setDocError("Choose at least one document to upload.");
      return;
    }
    setDocBusy(true);
    setDocError("");
    try {
      const result = await uploadCandidateDocuments(selected);
      setProfile(result?.profile || profile);
      setDocFiles({});
      push("Documents uploaded. Verification is now pending.");
    } catch (uploadError) {
      setDocError(uploadError.message || "Could not upload the documents.");
    } finally {
      setDocBusy(false);
    }
  };

  const deleteDocument = async () => {
    if (!removeDoc) return;
    setRemoveDocBusy(true);
    try {
      const result = await removeCandidateDocument(removeDoc);
      setProfile(result?.profile || profile);
      setRemoveDoc(null);
      push("Document removed.");
    } catch (removeError) {
      push(removeError.message || "Could not remove the document.", "error");
    } finally {
      setRemoveDocBusy(false);
    }
  };

  const overviewRows = (group) => {
    const details = profile?.details || {};
    const values = {
      ...details,
      full_name: profile?.full_name,
      city: profile?.city,
      ageText: profile?.age ? `${profile.age} years` : "",
      genderText: humanGender(profile?.gender),
      email: profile?.email,
      mobile: profile?.mobile || account?.username,
    };
    return group.rows
      .map(([label, key]) => [label, values[key]])
      .filter(([, value]) => text(value));
  };

  const renderField = (field, values, onChange, errors = {}) => {
    const error = Array.isArray(errors[field.name]) ? errors[field.name][0] : errors[field.name];
    const common = {
      id: field.name,
      name: field.name,
      value: values[field.name] ?? "",
      placeholder: field.placeholder,
      min: field.min,
      max: field.max,
      onChange: (event) => onChange(field.name, event.target.value),
    };

    return (
      <FormField
        key={field.name}
        label={field.label}
        name={field.name}
        required={field.required}
        error={error}
        className={field.full ? "cnd-field-wide" : ""}
      >
        {field.type === "textarea" ? (
          <textarea {...common} rows={field.rows || 3} />
        ) : field.type === "select" ? (
          <select {...common}>
            {field.options.map(([value, label]) => <option key={`${field.name}-${value}`} value={value}>{label}</option>)}
          </select>
        ) : (
          <input {...common} type={field.type || "text"} />
        )}
      </FormField>
    );
  };

  if (loading && !profile) {
    return (
      <CandidateLayout currentPath="/candidate/profile" candidateInfo={candidateInfo}>
        <PageHeader eyebrow="My profile" title="Your candidate profile" subtitle="Loading your profile details." />
        <DashboardSkeleton />
      </CandidateLayout>
    );
  }

  return (
    <CandidateLayout currentPath="/candidate/profile" candidateInfo={candidateInfo}>
      <Toasts items={toasts} onDismiss={dismiss} />
      <PageHeader
        eyebrow="My profile"
        title={profile ? "Your candidate profile" : "Create your candidate profile"}
        subtitle={profile
          ? "Manage your personal details, partner preferences and verification documents."
          : "Add the essentials now. You can complete the remaining details and preferences afterwards."}
        actions={profile ? (
          <a href="/candidate/matches" className="cnd-btn cnd-btn-primary">
            <Heart size={17} /> Find matches
          </a>
        ) : null}
      />

      {error ? <ErrorState message={error} onRetry={load} /> : null}

      {!profile ? (
        <SectionCard title="Start your profile" subtitle="These details are required to create your candidate profile" icon={UserRound}>
          {formNotice ? <InlineNote tone="error">{formNotice}</InlineNote> : null}
          <form className="cnd-profile-form" onSubmit={saveProfile}>
            <div className="cnd-form-grid">
              <div className="cnd-field-wide">
                {renderField({ name: "full_name", label: "Full name", required: true, placeholder: "Full name as per documents" }, form, updateField, fieldErrors)}
              </div>
              {renderField({ name: "gender", label: "Profile type", type: "select", required: true, options: [["", "Select"], ["groom", "Groom"], ["bride", "Bride"]] }, form, updateField, fieldErrors)}
              {renderField({ name: "birth_date", label: "Date of birth", type: "date", required: true }, form, updateField, fieldErrors)}
              <div className="cnd-field-wide">
                {renderField({ name: "email", label: "Email address", type: "email", placeholder: "you@example.com" }, form, updateField, fieldErrors)}
              </div>
            </div>
            <div className="cnd-form-actions">
              <button type="submit" className="cnd-btn cnd-btn-primary" disabled={saving}>
                {saving ? <Loader2 size={16} className="cnd-spin" /> : <Save size={16} />} Create profile
              </button>
            </div>
          </form>
        </SectionCard>
      ) : (
        <>
          <section className="cnd-profile-hero">
            <div className="cnd-profile-hero-photo">
              <div className="cnd-profile-photo-frame">
                {profile.photo_url ? (
                  <img src={profile.photo_url} alt={`${text(profile.full_name, "Candidate")} profile`} decoding="async" fetchPriority="high" />
                ) : (
                  <Avatar name={profile.full_name} size="xl" />
                )}
                {photoBusy ? (
                  <span className="cnd-photo-overlay"><Loader2 size={22} className="cnd-spin" /></span>
                ) : null}
              </div>
              <input ref={photoInput} type="file" accept="image/jpeg,image/png,image/webp" className="cnd-file-hidden" onChange={handlePhoto} />
              <div className="cnd-photo-actions">
                <button type="button" className="cnd-btn cnd-btn-light cnd-btn-sm" onClick={() => photoInput.current?.click()} disabled={photoBusy}>
                  <Camera size={15} /> {profile.photo_url ? "Change photo" : "Add photo"}
                </button>
                {profile.photo_url ? (
                  <button type="button" className="cnd-icon-btn cnd-photo-remove" onClick={() => setRemovePhotoOpen(true)} disabled={photoBusy} aria-label="Remove photo">
                    <Trash2 size={16} />
                  </button>
                ) : null}
              </div>
              {photoError ? <InlineNote tone="error">{photoError}</InlineNote> : null}
            </div>

            <div className="cnd-profile-hero-main">
              <div className="cnd-profile-hero-title">
                <div>
                  <span className="cnd-eyebrow">Candidate profile</span>
                  <h2>{text(profile.full_name, "Candidate")}</h2>
                  <p>{[profile.age ? `${profile.age} years` : "", humanGender(profile.gender), profile.city].filter(Boolean).join(" • ")}</p>
                </div>
              </div>
              <div className="cnd-profile-id-row">
                <span>ID: {text(profile.registration_number || profile.profile_id, "Not assigned")}</span>
                {profile.verification_status === "Completed" ? (
                  <span><BadgeCheck size={15} /> Verified profile</span>
                ) : null}
              </div>
              <div className="cnd-profile-hero-progress">
                <ProgressBar value={completion} label="Profile completion" />
                <ProgressRing value={completion} size={76} stroke={7} />
              </div>
              <div className="cnd-profile-hero-actions">
                <button type="button" className="cnd-btn cnd-btn-primary" onClick={() => setTab("edit")}>
                  <Settings2 size={16} /> Edit details
                </button>
                <button type="button" className="cnd-btn cnd-btn-ghost" onClick={() => setTab("preferences")}>
                  <Heart size={16} /> Preferences
                </button>
                <button type="button" className="cnd-btn cnd-btn-ghost" onClick={() => setTab("documents")}>
                  <ShieldCheck size={16} /> Documents
                </button>
              </div>
            </div>
          </section>

          <nav className="cnd-tabs" aria-label="Profile sections">
            {TABS.map(([key, label, Icon]) => (
              <button
                key={key}
                type="button"
                className={tab === key ? "active" : ""}
                onClick={() => setTab(key)}
                aria-current={tab === key ? "page" : undefined}
              >
                <Icon size={16} /> {label}
                {key === "documents" && uploadedDocuments.length ? <span className="cnd-tab-count">{uploadedDocuments.length}</span> : null}
              </button>
            ))}
          </nav>

          {tab === "overview" ? (
            <div className="cnd-profile-overview">
              <SectionCard title="About" subtitle="A short introduction" icon={UserRound} className="cnd-about-card">
                <p className="cnd-about-text">
                  {text(profile.details?.about, "Add an introduction so candidates can know you better. A thoughtful description helps you receive more genuine interests.")}
                </p>
                <div className="cnd-about-facts">
                  <span><MapPin size={15} /> {text(profile.city, "Location not added")}</span>
                  <span><GraduationCap size={15} /> {text(profile.details?.education, "Education not added")}</span>
                  <span><Briefcase size={15} /> {text(profile.details?.profession, "Profession not added")}</span>
                  <span><Heart size={15} /> {profile.preference ? "Preferences set" : "Preferences not set"}</span>
                </div>
              </SectionCard>

              {OVERVIEW_GROUPS.map((group) => {
                const rows = overviewRows(group);
                const Icon = group.icon;
                return (
                  <SectionCard key={group.title} title={group.title} icon={Icon}>
                    {rows.length ? (
                      <div className="cnd-detail-grid">
                        {rows.map(([label, value]) => (
                          <div key={label}>
                            <span>{label}</span>
                            <strong>{displayValue(value)}</strong>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <InlineNote>No {group.title.toLowerCase()} details added yet.</InlineNote>
                    )}
                  </SectionCard>
                );
              })}

              <SectionCard title="Account and verification" icon={ShieldCheck}>
                <div className="cnd-detail-grid">
                  <div><span>Registered mobile</span><strong>{text(profile.mobile || account?.username, "Not available")}</strong></div>
                  <div><span>Email</span><strong>{text(profile.email || account?.email, "Not added")}</strong></div>
                  <div><span>Member since</span><strong>{formatDate(profile.created_at, "Recently")}</strong></div>
                  <div><span>Documents uploaded</span><strong>{uploadedDocuments.length} of {DOCUMENT_LABELS.length}</strong></div>
                  <div><span>Profile completion</span><strong>{completion}%</strong></div>
                </div>
              </SectionCard>
            </div>
          ) : null}

          {tab === "edit" ? (
            <form className="cnd-profile-form" onSubmit={saveProfile}>
              {formNotice ? <InlineNote tone="error">{formNotice}</InlineNote> : null}
              <InlineNote>Fields marked with an asterisk are required. Changes are saved together when you submit the form.</InlineNote>
              {EDIT_SECTIONS.map((section) => {
                const Icon = section.icon;
                return (
                  <SectionCard key={section.key} title={section.title} subtitle={section.subtitle} icon={Icon}>
                    <div className="cnd-form-grid">
                      {section.fields.map((field) => renderField(field, form, updateField, fieldErrors))}
                    </div>
                  </SectionCard>
                );
              })}
              <div className="cnd-form-sticky">
                <span>Keep your profile accurate for better matches.</span>
                <button type="submit" className="cnd-btn cnd-btn-primary" disabled={saving}>
                  {saving ? <Loader2 size={16} className="cnd-spin" /> : <Save size={16} />} Save profile
                </button>
              </div>
            </form>
          ) : null}

          {tab === "preferences" ? (
            <form className="cnd-profile-form" onSubmit={savePartnerPreferences}>
              {preferenceNotice ? <InlineNote tone="error">{preferenceNotice}</InlineNote> : null}
              <div className="cnd-preference-note">
                <Heart size={20} />
                <div>
                  <strong>Your partner preferences</strong>
                  <p>These preferences directly influence your match score. Leave a field blank for no preference.</p>
                </div>
              </div>
              {PREFERENCE_SECTIONS.map((section) => {
                const Icon = section.icon;
                return (
                  <SectionCard key={section.key} title={section.title} subtitle={section.subtitle} icon={Icon}>
                    <div className="cnd-form-grid">
                      {section.fields.map((field) => renderField(field, preferenceForm, updatePreference, preferenceErrors))}
                    </div>
                  </SectionCard>
                );
              })}
              <div className="cnd-form-sticky">
                <span>You can update these preferences at any time.</span>
                <button type="submit" className="cnd-btn cnd-btn-primary" disabled={savingPreference}>
                  {savingPreference ? <Loader2 size={16} className="cnd-spin" /> : <Save size={16} />} Save preferences
                </button>
              </div>
            </form>
          ) : null}

          {tab === "documents" ? (
            <div className="cnd-documents">
              <SectionCard title="Verification documents" subtitle="Upload clear copies for faster verification" icon={ShieldCheck}>
                {docError ? <InlineNote tone="error">{docError}</InlineNote> : null}
                <div className="cnd-doc-list">
                  {DOCUMENT_LABELS.map(([key, label, hint]) => {
                    const doc = documents[key] || {};
                    const selected = docFiles[key];
                    return (
                      <article key={key} className="cnd-doc-row">
                        <span className={`cnd-doc-icon ${doc.file_url ? "uploaded" : ""}`}>
                          {doc.file_url ? <CheckCircle2 size={20} /> : <FileText size={20} />}
                        </span>
                        <div className="cnd-doc-info">
                          <strong>{label}</strong>
                          <span>{hint}</span>
                          {doc.file_url ? (
                            <small className={doc.status === "verified" ? "verified" : ""}>
                              Uploaded • {String(doc.status || "pending").replace(/_/g, " ")}
                              {doc.remarks ? ` • ${doc.remarks}` : ""}
                            </small>
                          ) : (
                            <small>Not uploaded</small>
                          )}
                        </div>
                        <div className="cnd-doc-actions">
                          {selected ? <span className="cnd-doc-selected">{selected.name} ({fileSizeLabel(selected.size)})</span> : null}
                          <label className="cnd-btn cnd-btn-ghost cnd-btn-sm cnd-upload-label">
                            <Upload size={15} /> {doc.file_url ? "Replace" : "Choose file"}
                            <input
                              type="file"
                              accept=".pdf,.jpg,.jpeg,.png"
                              onChange={(event) => chooseDocument(key, event)}
                            />
                          </label>
                          {doc.file_url ? (
                            <button type="button" className="cnd-icon-btn cnd-doc-delete" onClick={() => setRemoveDoc(key)} aria-label={`Remove ${label}`}>
                              <Trash2 size={16} />
                            </button>
                          ) : null}
                          {doc.file_url ? (
                            <button type="button" className="cnd-btn cnd-btn-ghost cnd-btn-sm" onClick={() => setViewDocument({ ...doc, label })}>
                              <Eye size={15} /> View
                            </button>
                          ) : null}
                        </div>
                      </article>
                    );
                  })}
                </div>
                <div className="cnd-doc-footer">
                  <span>{Object.values(docFiles).filter(Boolean).length} files selected</span>
                  <button type="button" className="cnd-btn cnd-btn-primary" onClick={uploadDocuments} disabled={docBusy}>
                    {docBusy ? <Loader2 size={16} className="cnd-spin" /> : <Upload size={16} />} Upload selected
                  </button>
                </div>
              </SectionCard>
              <InlineNote>
                Accepted formats: PDF, JPG, JPEG and PNG. Maximum size 5 MB per document. Uploaded documents are reviewed by the Manomilan team.
              </InlineNote>
            </div>
          ) : null}
        </>
      )}

      <ConfirmDialog
        open={removePhotoOpen}
        title="Remove profile photo?"
        message="Your profile will be shown without a photo until you upload a new one."
        confirmLabel="Remove photo"
        busy={photoBusy}
        onCancel={() => setRemovePhotoOpen(false)}
        onConfirm={deletePhoto}
      />

      <ConfirmDialog
        open={Boolean(removeDoc)}
        title="Remove this document?"
        message="The document will be removed from your profile and may delay verification."
        confirmLabel="Remove document"
        busy={removeDocBusy}
        onCancel={() => setRemoveDoc(null)}
        onConfirm={deleteDocument}
      />

      <Modal
        open={Boolean(viewDocument)}
        onClose={() => setViewDocument(null)}
        title={viewDocument?.label || "Document preview"}
        eyebrow="Uploaded document"
        size="lg"
      >
        {viewDocument?.file_url ? (
          <div className="cnd-document-viewer">
            {String(viewDocument.file_url).toLowerCase().includes(".pdf") ? (
              <iframe src={viewDocument.file_url} title={viewDocument.label || "Document preview"} />
            ) : (
              <img src={viewDocument.file_url} alt={viewDocument.label || "Uploaded document"} />
            )}
          </div>
        ) : null}
      </Modal>
    </CandidateLayout>
  );
}
