import { useEffect, useMemo, useRef, useState } from "react";
import {
  BadgeCheck, Briefcase, Camera, CheckCircle2, Copy, FileText, GraduationCap,
  Eye, Heart, Loader2, MapPin, Minus, Plus, Save, Settings2, ShieldCheck, Sparkles,
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
import { CASTE_OPTIONS, SUBCASTES } from "../../data/casteOptions";
import {
  createCandidateProfile, getCandidateProfile, removeCandidateDocument,
  removeCandidatePhoto, savePreferences, updateCandidateProfile,
  uploadCandidateAdditionalPhotos, uploadCandidateDocuments, uploadCandidatePhoto,
  uploadCandidateBodyPhotos, peekCandidateProfile,
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
      { name: "email", label: "Email address", type: "email", placeholder: "you@example.com" },
      {
        name: "source_of_information",
        label: "Source of information",
        type: "select",
        required: true,
        options: [
          ["", "Select source"],
          ["WhatsApp", "WhatsApp"],
          ["Instagram", "Instagram"],
          ["Facebook", "Facebook"],
          ["Poster/Flex in Temple", "Poster/Flex in Temple"],
          ["Outside Banner", "Outside Banner"],
          ["People References", "People References"],
          ["Call by Manomilan Executive", "Call by Manomilan Executive"],
          ["Family/Friends", "Family/Friends"],
        ],
      },
      { name: "religion", label: "Religion", required: true, readOnly: true },
      {
        name: "caste",
        label: "Caste",
        type: "select",
        required: true,
        options: [["", "Select caste"], ...CASTE_OPTIONS.map((caste) => [caste, caste])],
      },
      { name: "sub_caste", label: "Sub caste", type: "select" },
      {
        name: "marital_status",
        label: "Marital status",
        type: "select",
        required: true,
        options: [
          ["", "Select marital status"],
          ["Unmarried", "Unmarried"],
          ["Divorced", "Divorced"],
          ["Widowed", "Widowed"],
          ["Separated", "Separated"],
        ],
      },
      { name: "birth_date", label: "Date of birth", type: "date", required: true },
      { name: "height", label: "Height", required: true, placeholder: "5 ft 6 in" },
      { name: "weight", label: "Weight", required: true, placeholder: "60 kg" },
      { name: "having_specs_lenses", label: "Specs / lenses", type: "select", options: [["", "Select"], ["None", "None"], ["Specs", "Specs"], ["Lenses", "Lenses"], ["Both", "Both"]] },
      {
        name: "blood_group",
        label: "Blood group",
        type: "select",
        required: true,
        options: [
          ["", "Select blood group"],
          ["A+", "A+"],
          ["A-", "A-"],
          ["B+", "B+"],
          ["B-", "B-"],
          ["AB+", "AB+"],
          ["AB-", "AB-"],
          ["O+", "O+"],
          ["O-", "O-"],
        ],
      },
      { name: "mother_tongue", label: "Mother tongue", required: true, placeholder: "Marathi" },
      { name: "language", label: "Other languages known", placeholder: "Hindi, English..." },
      { name: "special_cases", label: "Special cases", placeholder: "None / details" },
      { name: "about", label: "About you", type: "textarea", rows: 5, full: true, placeholder: "Describe yourself, your values, interests and what you are looking for." },
      { name: "permanent_address", label: "Permanent address", type: "textarea", required: true, rows: 3, full: true, placeholder: "Permanent address" },
      { name: "address", label: "Current address", type: "textarea", required: true, rows: 3, full: true, placeholder: "Current residential address" },
    ],
  },
  {
    key: "horoscope",
    title: "Horoscope details",
    subtitle: "Birth time and horoscope information",
    icon: Sparkles,
    fields: [
      { name: "time_of_birth", label: "Time of birth", required: true, type: "time" },
      { name: "place_of_birth", label: "Place of birth", required: true, placeholder: "City or town" },
      { name: "birth_time_ampm", label: "AM / PM", type: "select", options: [["", "Select"], ["AM", "AM"], ["PM", "PM"]] },
      {
        name: "horoscope_caste",
        label: "Horoscope caste",
        type: "select",
        options: [["", "Select caste"], ...CASTE_OPTIONS.map((caste) => [caste, caste])],
      },
      { name: "horoscope_sub_caste", label: "Horoscope sub caste", type: "select" },
      { name: "horoscope_dob", label: "Horoscope date of birth", type: "date" },
    ],
  },
  {
    key: "education",
    title: "Educational details",
    subtitle: "Your qualifications and institutions",
    icon: GraduationCap,
    fields: [
      {
        name: "education",
        label: "Education",
        type: "select",
        required: true,
        className: "cnd-education-level",
        options: [
          ["", "Select education"],
          ["10th", "10th"],
          ["12th", "12th"],
          ["UG", "UG"],
          ["PG", "PG"],
          ["PhD", "PhD"],
          ["Diploma", "Diploma"],
        ],
      },
      { name: "degree", label: "Degree / diploma", required: true, placeholder: "Degree name", className: "cnd-education-degree" },
      { name: "degree_2", label: "Additional degree", placeholder: "Second degree", className: "cnd-education-degree-extra" },
      { name: "college", label: "University / college", placeholder: "Institute name", className: "cnd-education-college" },
      { name: "city_of_education", label: "City of education", placeholder: "City", className: "cnd-education-city" },
    ],
  },
  {
    key: "profession",
    title: "Professional details",
    subtitle: "Work and income",
    icon: Briefcase,
    fields: [
      { name: "profession", label: "Profession", required: true, placeholder: "Software engineer" },
      { name: "field_of_working", label: "Working field", required: true, placeholder: "Information technology" },
      { name: "organisation", label: "Organisation", placeholder: "Company name" },
      { name: "designation", label: "Designation", placeholder: "Senior engineer" },
      { name: "professional_city", label: "Work city", required: true, placeholder: "City" },
      { name: "length_of_employment", label: "Experience", placeholder: "5 years" },
      { name: "monthly_income", label: "Monthly income (INR)", required: true, placeholder: "Monthly income" },
      { name: "annual_income", label: "Annual income (INR)", placeholder: "Annual income" },
    ],
  },
  {
    key: "medical",
    title: "Medical details",
    subtitle: "Share medical information if applicable",
    icon: ShieldCheck,
    fields: [
      { name: "has_medical_history", label: "Any medical history?", required: true, type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      {
        name: "personal_medical_history",
        label: "Personal medical history",
        type: "textarea",
        rows: 3,
        full: true,
        placeholder: "Add details if applicable",
        showWhen: { field: "has_medical_history", value: "Yes" },
      },
      { name: "current_medical_problems", label: "Current medical problems", type: "textarea", rows: 3, full: true, placeholder: "Add details if applicable" },
      { name: "medications", label: "Medications", placeholder: "Add details if applicable" },
    ],
  },
  {
    key: "family",
    title: "Family details",
    subtitle: "Tell candidates about your family",
    icon: Users,
    fields: [
      { name: "father_name", label: "Father's name", required: true, placeholder: "Father's name" },
      { name: "father_alive", label: "Father alive", required: true, type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "father_contact", label: "Father's contact number", type: "tel", inputMode: "numeric", maxLength: 10, placeholder: "10-digit contact number", showWhen: { field: "father_alive", value: "Yes" } },
      { name: "father_occupation", label: "Father's occupation", placeholder: "Occupation", showWhen: { field: "father_alive", value: "Yes" } },
      { name: "father_native_place", label: "Father's native place", placeholder: "Native place", showWhen: { field: "father_alive", value: "Yes" } },
      { name: "mother_name", label: "Mother's name", required: true, placeholder: "Mother's name" },
      { name: "mother_alive", label: "Mother alive", required: true, type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "mother_contact", label: "Mother's contact number", type: "tel", inputMode: "numeric", maxLength: 10, placeholder: "10-digit contact number", showWhen: { field: "mother_alive", value: "Yes" } },
      { name: "mother_occupation", label: "Mother's occupation", placeholder: "Occupation", showWhen: { field: "mother_alive", value: "Yes" } },
      { name: "mother_native_place", label: "Mother's native place", placeholder: "Native place", showWhen: { field: "mother_alive", value: "Yes" } },
      { name: "brothers", label: "Number of brothers", required: true, type: "number", min: 0, max: 20, step: 1, inputMode: "numeric" },
      { name: "brothers_married", label: "Brothers married", type: "number", min: 0, max: 20, step: 1, inputMode: "numeric" },
      { name: "sisters", label: "Number of sisters", required: true, type: "number", min: 0, max: 20, step: 1, inputMode: "numeric" },
      { name: "sisters_married", label: "Sisters married", type: "number", min: 0, max: 20, step: 1, inputMode: "numeric" },
      { name: "parents_intercaste_marriage", label: "Parents inter-caste marriage?", type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "parents_different_caste", label: "If yes, which different caste", placeholder: "Caste details", showWhen: { field: "parents_intercaste_marriage", value: "Yes" } },
      {
        name: "family_values",
        label: "Family values",
        type: "select",
        options: [["", "Select family values"], ["Traditional", "Traditional"], ["Modern", "Modern"], ["Liberal", "Liberal"]],
      },
      {
        name: "family_financial_status",
        label: "Financial status",
        type: "select",
        options: [["", "Select financial status"], ["Lower", "Lower"], ["Middle", "Middle"], ["Upper", "Upper"]],
      },
      { name: "family_annual_income", label: "Family income (annual)", placeholder: "Annual income" },
      { name: "profile_creator_name", label: "Profile created by", placeholder: "Creator's name" },
      { name: "profile_creator_relation", label: "Creator's relation to candidate", placeholder: "Self, parent..." },
    ],
  },
  {
    key: "reference",
    title: "Reference person's details",
    subtitle: "Contact details for your reference",
    icon: UserRound,
    fields: [
      { name: "reference_name", label: "Name", required: true, placeholder: "Reference person's name" },
      { name: "reference_relation", label: "Relation to candidate", required: true, placeholder: "Relation to you" },
      { name: "reference_address", label: "Address", placeholder: "Address" },
      { name: "reference_contact", label: "Contact", type: "tel", inputMode: "numeric", maxLength: 10, placeholder: "10-digit mobile number" },
      { name: "reference_email", label: "Email", type: "email", placeholder: "Email address" },
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
      { name: "preferred_religion", label: "Preferred religion", readOnly: true },
      { name: "preferred_caste", label: "Preferred caste", type: "select", options: [["Any", "Any"], ["Shwetambar", "Shwetambar"], ["Digambar", "Digambar"]] },
      { name: "preferred_sub_caste", label: "Preferred sub caste", type: "select" },
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
      { name: "family_type", label: "Family type", type: "select", options: [["", "Select family type"], ["Joint", "Joint"], ["Nuclear", "Nuclear"]] },
      { name: "financial_background", label: "Financial background", type: "select", options: [["", "Select financial background"], ["Lower", "Lower"], ["Middle", "Middle"], ["Upper", "Upper"]] },
      { name: "family_values", label: "Family values", type: "select", options: [["", "Select family values"], ["Traditional", "Traditional"], ["Modern", "Modern"], ["Liberal", "Liberal"]] },
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
  ["identificationProof", "Identity proof *", "Aadhaar, PAN or passport"],
  ["birthDateProof", "Birth date proof *", "Birth certificate or school certificate"],
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
    title: "Other details",
    icon: Sparkles,
    rows: [
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

const buildForm = (profile, user = null) => {
  const details = profile?.details || {};
  const gender = profile?.gender || profile?.groomOrBride || user?.gender || user?.groomOrBride || "";
  const form = {
    full_name: profile?.full_name || profile?.fullName || user?.name || user?.full_name || user?.fullName || "",
    gender: String(gender).toLowerCase(),
    birth_date: dateValue(profile?.birth_date || profile?.birthDate || user?.birth_date || user?.birthDate),
    email: profile?.email || user?.email || "",
    mobile: profile?.mobile || user?.mobile || user?.username || "",
  };
  ALL_EDIT_FIELDS.forEach(({ name }) => {
    if (!(name in form)) form[name] = details[name] ?? "";
  });
  if (!form.horoscope_caste) form.horoscope_caste = form.caste;
  if (!form.horoscope_sub_caste) form.horoscope_sub_caste = form.sub_caste;
  form.specialisations = Array.isArray(details.specialisations)
    ? [...details.specialisations]
    : [details.specialisation, details.specialisation_2].filter(Boolean);
  form.additional_qualifications = Array.isArray(details.additional_qualifications)
    ? [...details.additional_qualifications]
    : [details.additional_education, details.additional_education_2].filter(Boolean);
  if (!form.specialisations.length) form.specialisations = [""];
  if (!form.additional_qualifications.length) form.additional_qualifications = [""];
  form.religion = "Jain";
  form.time_of_birth = timeValue(details.time_of_birth);
  return form;
};

const buildPreferenceForm = (preference) => {
  const form = {};
  ALL_PREFERENCE_FIELDS.forEach(({ name }) => {
    form[name] = preference?.[name] ?? "";
  });
  form.preferred_religion = "Jain";
  return form;
};

const tabFromUrl = () => {
  if (typeof window === "undefined") return "overview";
  const value = new URLSearchParams(window.location.search).get("tab");
  return TABS.some(([key]) => key === value) ? value : "overview";
};

const candidateDraftKey = (user) => {
  const identity = user?.id ?? user?.username;
  return identity == null || String(identity).trim() === ""
    ? ""
    : `candidate_profile_draft:${encodeURIComponent(String(identity).trim().toLowerCase())}`;
};

const candidatePreferenceDraftKey = (user) => {
  const identity = user?.id ?? user?.username;
  return identity == null || String(identity).trim() === ""
    ? ""
    : `candidate_preference_draft:${encodeURIComponent(String(identity).trim().toLowerCase())}`;
};

const readCandidateDraft = (key) => {
  if (!key) return null;
  try {
    const saved = JSON.parse(window.localStorage.getItem(key));
    if (!saved || typeof saved !== "object" || Array.isArray(saved)) return null;
    return saved;
  } catch (draftError) {
    console.error("Unable to read the candidate profile draft.", draftError);
    return null;
  }
};

const writeCandidateDraft = (key, draft) => {
  if (!key) return;
  try {
    window.localStorage.setItem(key, JSON.stringify(draft));
  } catch (draftError) {
    console.error("Unable to save the candidate profile draft.", draftError);
  }
};

const clearCandidateDraft = (key) => {
  if (!key) return;
  try {
    window.localStorage.removeItem(key);
  } catch (draftError) {
    console.error("Unable to clear the saved candidate draft.", draftError);
  }
};

const restoreCandidateDraft = (baseForm, draft) => {
  if (!draft) return baseForm;
  const restored = { ...baseForm, ...draft };
  for (const field of ["specialisations", "additional_qualifications"]) {
    restored[field] = Array.isArray(draft[field])
      ? draft[field].filter((value) => typeof value === "string")
      : baseForm[field];
    if (!restored[field].length) restored[field] = [""];
  }
  return restored;
};

const restorePreferenceDraft = (baseForm, draft) => {
  if (!draft) return baseForm;
  const restored = { ...baseForm };
  ALL_PREFERENCE_FIELDS.forEach(({ name }) => {
    if (typeof draft[name] === "string") restored[name] = draft[name];
  });
  restored.preferred_religion = "Jain";
  return restored;
};

export default function CandidateProfile() {
  const cached = useMemo(() => getCachedHomeData(), []);
  const cachedProfileResult = useMemo(() => peekCandidateProfile(), []);
  const initialProfile = cachedProfileResult?.profile || cached?.profile || null;
  const initialAccount = cachedProfileResult?.user || cached?.user || null;
  const [profile, setProfile] = useState(initialProfile);
  const [account, setAccount] = useState(initialAccount);
  const [loading, setLoading] = useState(!initialProfile);
  const [draftLoaded, setDraftLoaded] = useState(false);
  const [error, setError] = useState("");
  const [tab, setTab] = useState(tabFromUrl);
  const [form, setForm] = useState(() => buildForm(initialProfile, initialAccount));
  const [preferenceForm, setPreferenceForm] = useState(() => buildPreferenceForm(initialProfile?.preference));
  const [preferenceDraftPending, setPreferenceDraftPending] = useState(false);
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
  const [additionalPhotoFiles, setAdditionalPhotoFiles] = useState([]);
  const [additionalPhotoBusy, setAdditionalPhotoBusy] = useState(false);
  const [additionalPhotoError, setAdditionalPhotoError] = useState("");
  const [removeDoc, setRemoveDoc] = useState(null);
  const [removeDocBusy, setRemoveDocBusy] = useState(false);
  const [viewDocument, setViewDocument] = useState(null);
  const photoInput = useRef(null);
  const [bodyPhotoBusy, setBodyPhotoBusy] = useState(false);
  const [bodyPhotoError, setBodyPhotoError] = useState("");
  const { toasts, push, dismiss } = useToasts();

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      const result = await getCandidateProfile();
      const currentUser = result?.user || cached?.user || account || null;
      const baseForm = buildForm(result?.profile, currentUser);
      const complete = Number(result?.profile?.profile_completion || 0) >= 100;
      const draft = complete ? null : readCandidateDraft(candidateDraftKey(currentUser));
      const preferenceDraft = readCandidateDraft(candidatePreferenceDraftKey(currentUser));
      setProfile(result?.profile || null);
      setAccount(currentUser);
      setForm(restoreCandidateDraft(baseForm, draft));
      setPreferenceForm(restorePreferenceDraft(
        buildPreferenceForm(result?.profile?.preference),
        preferenceDraft,
      ));
      setPreferenceDraftPending(Boolean(preferenceDraft));
      setDraftLoaded(true);
      const requestedTab = new URLSearchParams(window.location.search).get("tab");
      setTab(complete
        ? tabFromUrl()
        : ["overview", "documents"].includes(requestedTab)
          ? requestedTab
          : "edit");
    } catch (loadError) {
      setError(loadError.message || "Could not load your profile.");
      const complete = Number(profile?.profile_completion || 0) >= 100;
      if (!complete) {
        setForm(restoreCandidateDraft(
          buildForm(profile, account),
          readCandidateDraft(candidateDraftKey(account)),
        ));
      }
      const preferenceDraft = readCandidateDraft(candidatePreferenceDraftKey(account));
      setPreferenceForm(restorePreferenceDraft(
        buildPreferenceForm(profile?.preference),
        preferenceDraft,
      ));
      setPreferenceDraftPending(Boolean(preferenceDraft));
      setDraftLoaded(true);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    Promise.resolve().then(load);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const profileDraftStorageKey = candidateDraftKey(account);
  const preferenceDraftStorageKey = candidatePreferenceDraftKey(account);
  const completion = Number(profile?.profile_completion || 0);
  const profileComplete = completion >= 100;

  useEffect(() => {
    if (!draftLoaded || profileComplete || !profileDraftStorageKey) return;
    writeCandidateDraft(profileDraftStorageKey, form);
  }, [draftLoaded, form, profileComplete, profileDraftStorageKey]);

  useEffect(() => {
    if (!draftLoaded || !preferenceDraftPending || !preferenceDraftStorageKey) return;
    writeCandidateDraft(preferenceDraftStorageKey, preferenceForm);
  }, [draftLoaded, preferenceDraftPending, preferenceDraftStorageKey, preferenceForm]);

  const candidateInfo = {
    name: profile?.full_name || account?.name || account?.username,
    id: profile?.registration_number || profile?.profile_id || account?.username,
    photo: profile?.photo_url || "",
  };

  const conditionalRequiredFields = new Set([
    ...(["Shwetambar", "Digambar"].includes(form.caste) ? ["sub_caste"] : []),
    ...(form.father_alive === "Yes" ? ["father_occupation", "father_contact"] : []),
    ...(form.mother_alive === "Yes" ? ["mother_occupation", "mother_contact"] : []),
    ...(Number(form.brothers) > 0 ? ["brothers_married"] : []),
    ...(Number(form.sisters) > 0 ? ["sisters_married"] : []),
  ]);
  const visibleTabs = profileComplete
    ? TABS
    : TABS.filter(([key]) => key !== "preferences");
  const activeTab = !profileComplete && tab === "preferences" ? "edit" : tab;
  const documents = profile?.documents || {};
  const uploadedDocuments = DOCUMENT_LABELS.filter(([key]) => documents[key]?.file_url);

  const updateField = (name, value) => {
    const siblingCountFields = ["brothers", "brothers_married", "sisters", "sisters_married"];
    if (siblingCountFields.includes(name) && !/^\d*$/.test(value)) return;
    if (name === "brothers_married" && value !== ""
      && (form.brothers === "" || Number(value) > Number(form.brothers))) return;
    if (name === "sisters_married" && value !== ""
      && (form.sisters === "" || Number(value) > Number(form.sisters))) return;
    const fieldValue = ["father_contact", "mother_contact", "reference_contact"].includes(name)
      ? value.replace(/\D/g, "").slice(0, 10)
      : siblingCountFields.includes(name)
        ? value
        : value;
    setForm((current) => {
      const next = { ...current, [name]: fieldValue };
      if (name === "brothers" && next.brothers !== ""
        && Number(next.brothers_married || 0) > Number(next.brothers)) {
        next.brothers_married = next.brothers;
      }
      if (name === "sisters" && next.sisters !== ""
        && Number(next.sisters_married || 0) > Number(next.sisters)) {
        next.sisters_married = next.sisters;
      }
      if (name === "caste") {
        next.sub_caste = "";
        next.horoscope_caste = value;
        next.horoscope_sub_caste = "";
      }
      if (name === "sub_caste") next.horoscope_sub_caste = value;
      if (name === "horoscope_caste") next.horoscope_sub_caste = "";
      if (name === "father_alive" && fieldValue !== "Yes") {
        next.father_contact = "";
        next.father_occupation = "";
        next.father_native_place = "";
      }
      if (name === "mother_alive" && fieldValue !== "Yes") {
        next.mother_contact = "";
        next.mother_occupation = "";
        next.mother_native_place = "";
      }
      if (name === "parents_intercaste_marriage" && fieldValue !== "Yes") {
        next.parents_different_caste = "";
      }
      if (name === "monthly_income") {
        const monthlyAmount = Number(String(fieldValue).replace(/,/g, ""));
        next.annual_income = String(fieldValue).trim() && Number.isFinite(monthlyAmount)
          ? String(Number((monthlyAmount * 12).toFixed(2)))
          : "";
      }
      return next;
    });
    setFieldErrors((current) => ({
      ...current,
      [name]: undefined,
      ...(name === "caste" ? {
        sub_caste: undefined,
        horoscope_caste: undefined,
        horoscope_sub_caste: undefined,
      } : {}),
      ...(name === "sub_caste" ? { horoscope_sub_caste: undefined } : {}),
      ...(name === "horoscope_caste" ? { horoscope_sub_caste: undefined } : {}),
      ...(name === "father_alive" ? {
        father_contact: undefined,
        father_occupation: undefined,
        father_native_place: undefined,
      } : {}),
      ...(name === "mother_alive" ? {
        mother_contact: undefined,
        mother_occupation: undefined,
        mother_native_place: undefined,
      } : {}),
      ...(name === "parents_intercaste_marriage" ? { parents_different_caste: undefined } : {}),
      ...(name === "monthly_income" ? { annual_income: undefined } : {}),
      ...(name === "brothers" || name === "brothers_married" ? {
        brothers: undefined,
        brothers_married: undefined,
      } : {}),
      ...(name === "sisters" || name === "sisters_married" ? {
        sisters: undefined,
        sisters_married: undefined,
      } : {}),
    }));
  };

  const updateEducationList = (name, index, value) => {
    setForm((current) => ({
      ...current,
      [name]: current[name].map((item, itemIndex) => itemIndex === index ? value : item),
    }));
  };

  const addEducationListItem = (name) => {
    setForm((current) => ({ ...current, [name]: [...current[name], ""] }));
  };

  const removeEducationListItem = (name, index) => {
    setForm((current) => ({
      ...current,
      [name]: current[name].length > 1
        ? current[name].filter((_, itemIndex) => itemIndex !== index)
        : [""],
    }));
  };

  const applyProfileResponse = (nextProfile) => {
    if (!nextProfile) return;
    setProfile(nextProfile);
    if (Number(nextProfile.profile_completion || 0) >= 100) {
      clearCandidateDraft(profileDraftStorageKey);
    }
  };

  const updatePreference = (name, value) => {
    if (name === "preferred_religion") return;
    setPreferenceDraftPending(true);
    setPreferenceForm((current) => ({
      ...current,
      [name]: value,
      ...(name === "preferred_caste"
        ? { preferred_sub_caste: value === "Any" ? "Any" : "" }
        : {}),
    }));
    setPreferenceErrors((current) => ({
      ...current,
      [name]: undefined,
      ...(name === "preferred_caste" ? { preferred_sub_caste: undefined } : {}),
    }));
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
      const currentUser = result?.user || account;
      applyProfileResponse(result?.profile);
      setAccount(currentUser);
      setForm((current) => ({ ...buildForm(result?.profile, currentUser), ...current }));
      setPreferenceForm(buildPreferenceForm(result?.profile?.preference));
      push(profile ? "Your profile has been updated." : "Your candidate profile has been created.");
      const nextTab = Number(result?.profile?.profile_completion || 0) >= 100 ? "overview" : "edit";
      setTab(nextTab);
      if (!profile) {
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set("tab", nextTab);
        window.history.replaceState(window.history.state, "", nextUrl);
      }
    } catch (saveError) {
      setFieldErrors(saveError.errors || {});
      setFormNotice(saveError.message || "Could not save your profile.");
    } finally {
      setSaving(false);
    }
  };

  const savePartnerPreferences = async (event) => {
    event?.preventDefault();
    setSavingPreference(true);
    setPreferenceNotice("");
    setPreferenceErrors({});
    try {
      const result = await savePreferences(preferenceForm);
      setProfile((current) => current ? { ...current, preference: result?.preference, has_preferences: true } : current);
      setPreferenceForm(buildPreferenceForm(result?.preference));
      setPreferenceDraftPending(false);
      clearCandidateDraft(preferenceDraftStorageKey);
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
      applyProfileResponse(result?.profile);
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
      applyProfileResponse(result?.profile);
      setRemovePhotoOpen(false);
      push("Profile photo removed.");
    } catch (removeError) {
      push(removeError.message || "Could not remove the photo.", "error");
    } finally {
      setPhotoBusy(false);
    }
  };

  const handleBodyPhoto = async (field, event) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
      setBodyPhotoError("Choose a JPG, PNG or WebP image.");
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setBodyPhotoError("The photo must be 5 MB or smaller.");
      return;
    }

    setBodyPhotoBusy(true);
    setBodyPhotoError("");
    try {
      const result = await uploadCandidateBodyPhotos({ [field]: file });
      applyProfileResponse(result?.profile);
      push("Photo updated successfully.");
    } catch (uploadError) {
      setBodyPhotoError(uploadError.message || "Could not upload the photo.");
    } finally {
      setBodyPhotoBusy(false);
    }
  };

  const chooseDocument = (key, event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setDocFiles((current) => ({ ...current, [key]: file }));
    setDocError("");
  };

  const chooseAdditionalPhotos = (event) => {
    const files = Array.from(event.target.files || []);
    event.target.value = "";
    if (!files.length) return;
    const existingCount = profile?.additional_photo_urls?.length || 0;
    if (files.length + existingCount > 4) {
      setAdditionalPhotoError(`You can keep up to four additional photos. ${4 - existingCount} slot(s) available.`);
      return;
    }
    if (files.some((file) => !["image/jpeg", "image/png", "image/webp"].includes(file.type))) {
      setAdditionalPhotoError("Choose JPG, PNG or WebP photos only.");
      return;
    }
    if (files.some((file) => file.size > 5 * 1024 * 1024)) {
      setAdditionalPhotoError("Each photo must be 5 MB or smaller.");
      return;
    }
    setAdditionalPhotoFiles(files);
    setAdditionalPhotoError("");
  };

  const uploadAdditionalPhotos = async () => {
    if (!additionalPhotoFiles.length) return;
    setAdditionalPhotoBusy(true);
    setAdditionalPhotoError("");
    try {
      const result = await uploadCandidateAdditionalPhotos(additionalPhotoFiles);
      applyProfileResponse(result?.profile);
      setAdditionalPhotoFiles([]);
      push("Additional photos uploaded.");
    } catch (uploadError) {
      setAdditionalPhotoError(uploadError.message || "Could not upload additional photos.");
    } finally {
      setAdditionalPhotoBusy(false);
    }
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
      applyProfileResponse(result?.profile);
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
      applyProfileResponse(result?.profile);
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
    const subCasteParent = field.name === "sub_caste"
      ? "caste"
      : field.name === "horoscope_sub_caste"
        ? "horoscope_caste"
        : field.name === "preferred_sub_caste"
          ? "preferred_caste"
          : null;
    const preferenceSubCaste = field.name === "preferred_sub_caste";
    const options = subCasteParent
      ? [
          ["", "Select sub caste"],
          ...(preferenceSubCaste ? [["Any", "Any"]] : []),
          ...(SUBCASTES[values[subCasteParent]] || [])
            .filter((subCaste) => subCaste !== "Any")
            .map((subCaste) => [subCaste, subCaste]),
        ]
      : [...(field.options || [])];
    const casteField = field.name === "caste" || field.name === "horoscope_caste";
    const siblingLimit = field.name === "brothers_married"
      ? values.brothers
      : field.name === "sisters_married"
        ? values.sisters
        : null;
    if (casteField && values[field.name] && !options.some(([value]) => value === values[field.name])) {
      options.push([values[field.name], values[field.name]]);
    }
    if (subCasteParent && values[field.name] && !options.some(([value]) => value === values[field.name])) {
      options.push([values[field.name], values[field.name]]);
    }
    const common = {
      id: field.name,
      name: field.name,
      value: values[field.name] ?? "",
      placeholder: field.placeholder,
      min: field.min,
      max: siblingLimit !== null ? siblingLimit || 0 : field.max,
      step: field.step,
      maxLength: field.maxLength,
      inputMode: field.inputMode,
      readOnly: field.readOnly,
      onChange: (event) => onChange(field.name, event.target.value),
    };

    return (
      <FormField
        key={field.name}
        label={field.label}
        name={field.name}
        required={field.required}
        error={error}
        className={[field.full ? "cnd-field-wide" : "", field.className].filter(Boolean).join(" ")}
      >
        {field.type === "textarea" ? (
          <>
            {field.name === "address" ? (
              <button
                type="button"
                className="cnd-btn cnd-btn-light cnd-btn-sm cnd-address-copy"
                aria-label="Copy permanent address to current address"
                title="Copy permanent address to current address"
                onClick={() => onChange("address", values.permanent_address || "")}
              >
                <Copy size={16} aria-hidden="true" />
                <span>Same as Permanent Address</span>
              </button>
            ) : null}
            <textarea {...common} rows={field.rows || 3} />
          </>
        ) : field.type === "select" ? (
          <select {...common}>
            {options.map(([value, label]) => <option key={`${field.name}-${value}`} value={value}>{label}</option>)}
          </select>
        ) : (
          <input {...common} type={field.type || "text"} />
        )}
      </FormField>
    );
  };

  const renderEducationRepeater = (name, label, placeholder, className) => (
    <div className={`cnd-education-repeater ${className}`} key={name}>
      <span className="cnd-education-repeater-label">{label}<span aria-hidden="true">*</span></span>
      <div className="cnd-education-repeater-rows">
        {form[name].map((value, index) => (
          <div className="cnd-education-repeater-row" key={`${name}-${index}`}>
            <input
              className="cnd-education-repeater-input"
              id={`${name}-${index}`}
              name={`${name}[${index}]`}
              value={value}
              placeholder={placeholder}
              onChange={(event) => updateEducationList(name, index, event.target.value)}
            />
            <button
              type="button"
              className="cnd-education-repeater-remove"
              aria-label={`Remove ${label.toLowerCase()} ${index + 1}`}
              onClick={() => removeEducationListItem(name, index)}
            >
              <Minus size={18} />
            </button>
            {index === form[name].length - 1 ? (
              <button
                type="button"
                className="cnd-education-repeater-add"
                aria-label={`Add ${label.toLowerCase()}`}
                onClick={() => addEducationListItem(name)}
              >
                <Plus size={18} />
              </button>
            ) : null}
          </div>
        ))}
      </div>
    </div>
  );

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
          ? profileComplete
            ? "Manage your personal details, partner preferences and verification documents."
            : "Complete the required profile details, photos and documents to unlock the candidate portal."
          : "Add the essentials now. You can complete the remaining details and preferences afterwards."}
        actions={profile && profileComplete ? (
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
          {!profileComplete ? (
            <InlineNote>
              Your profile is {completion}% complete. Fill every required field, add the required photos, and upload your identity and birth date proofs. Other candidate pages unlock when completion reaches 100%.
            </InlineNote>
          ) : null}
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
                  <Camera size={15} /> {profile.photo_url ? "Change photo" : "Add photo *"}
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
                {profileComplete ? (
                  <button type="button" className="cnd-btn cnd-btn-ghost" onClick={() => setTab("preferences")}>
                    <Heart size={16} /> Preferences
                  </button>
                ) : null}
                <button type="button" className="cnd-btn cnd-btn-ghost" onClick={() => setTab("documents")}>
                  <ShieldCheck size={16} /> Documents
                </button>
              </div>
            </div>
          </section>

          <nav className="cnd-tabs" aria-label="Profile sections">
            {visibleTabs.map(([key, label, Icon]) => (
              <button
                key={key}
                type="button"
                className={activeTab === key ? "active" : ""}
                onClick={() => setTab(key)}
                aria-current={activeTab === key ? "page" : undefined}
              >
                <Icon size={16} /> {label}
                {key === "documents" && uploadedDocuments.length ? <span className="cnd-tab-count">{uploadedDocuments.length}</span> : null}
              </button>
            ))}
          </nav>

          {activeTab === "overview" ? (
            <div className="cnd-profile-overview">
              <SectionCard title="Photo requirements" subtitle="All three photos are required. Upload clear JPG, PNG or WebP photos (maximum 5 MB each)." icon={Camera}>
                <div className="cnd-body-photo-grid">
                  {[
                    ["full_body_photo", "Full Body Photo *", profile.full_body_photo_url],
                    ["half_body_photo", "Half Body Photo *", profile.half_body_photo_url],
                    ["selfie_photo", "Selfie Photo *", profile.selfie_photo_url],
                  ].map(([field, label, url]) => (
                    <div className="cnd-body-photo-card" key={field}>
                      <div className="cnd-body-photo-preview">
                        {url ? <img src={url} alt={label} /> : <Camera size={30} />}
                        {bodyPhotoBusy ? <span className="cnd-photo-overlay"><Loader2 size={22} className="cnd-spin" /></span> : null}
                      </div>
                      <strong>{label}</strong>
                      <span>{url ? "Uploaded" : "Not uploaded"}</span>
                      <label className="cnd-btn cnd-btn-light cnd-btn-sm">
                        {url ? "Replace photo" : "Upload photo"}
                        <input
                          className="cnd-file-hidden"
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          disabled={bodyPhotoBusy}
                          onChange={(event) => handleBodyPhoto(field, event)}
                        />
                      </label>
                    </div>
                  ))}
                </div>
                {bodyPhotoError ? <InlineNote tone="error">{bodyPhotoError}</InlineNote> : null}
              </SectionCard>
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

          {activeTab === "edit" ? (
            <form className="cnd-profile-form cnd-profile-editor" onSubmit={saveProfile}>
              {formNotice ? <InlineNote tone="error">{formNotice}</InlineNote> : null}
              <InlineNote>Fields marked with an asterisk are required. Save profile details, preferences, photos and documents from their respective sections on this page.</InlineNote>
              {EDIT_SECTIONS.map((section) => {
                const Icon = section.icon;
                return (
                  <SectionCard key={section.key} title={section.title} subtitle={section.subtitle} icon={Icon}>
                    {section.key === "basic" ? (
                      <div className="cnd-body-photo-grid">
                        {[
                          ["full_body_photo", "Full body photo *", profile.full_body_photo_url],
                          ["half_body_photo", "Half body photo *", profile.half_body_photo_url],
                          ["selfie_photo", "Selfie photo *", profile.selfie_photo_url],
                        ].map(([field, label, url]) => (
                          <div className="cnd-body-photo-card" key={field}>
                            <div className="cnd-body-photo-preview">
                              {url ? <img src={url} alt={label} /> : <Camera size={30} />}
                              {bodyPhotoBusy ? <span className="cnd-photo-overlay"><Loader2 size={22} className="cnd-spin" /></span> : null}
                            </div>
                            <strong>{label}</strong>
                            <span>{url ? "Uploaded" : "Not uploaded"}</span>
                            <label className="cnd-btn cnd-btn-light cnd-btn-sm">
                              {url ? "Replace photo" : "Upload photo"}
                              <input
                                className="cnd-file-hidden"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                disabled={bodyPhotoBusy}
                                onChange={(event) => handleBodyPhoto(field, event)}
                              />
                            </label>
                          </div>
                        ))}
                      </div>
                    ) : null}
                    {section.key === "medical" ? (
                      <InlineNote>Share health details only when applicable. This information is handled as part of your private profile.</InlineNote>
                    ) : null}
                    {section.key === "education" ? (
                      <div className="cnd-form-grid cnd-education-grid">
                        {section.fields.slice(0, 3).map((field) => renderField({
                          ...field,
                          required: field.required || conditionalRequiredFields.has(field.name),
                        }, form, updateField, fieldErrors))}
                        {renderEducationRepeater(
                          "specialisations",
                          "Specialisation",
                          "Specialisation",
                          "cnd-education-specialisations",
                        )}
                        {section.fields.slice(3).map((field) => renderField({
                          ...field,
                          required: field.required || conditionalRequiredFields.has(field.name),
                        }, form, updateField, fieldErrors))}
                        {renderEducationRepeater(
                          "additional_qualifications",
                          "Additional education",
                          "Additional qualification",
                          "cnd-education-additional",
                        )}
                      </div>
                    ) : (
                      <div className="cnd-form-grid">
                        {section.fields
                          .filter((field) => !field.showWhen || form[field.showWhen.field] === field.showWhen.value)
                          .map((field) => renderField({
                            ...field,
                            required: field.required || conditionalRequiredFields.has(field.name),
                          }, form, updateField, fieldErrors))}
                      </div>
                    )}
                  </SectionCard>
                );
              })}
              <SectionCard title="Document details" subtitle="Upload the proofs required to complete verification" icon={FileText}>
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
                          ) : <small>Not uploaded</small>}
                        </div>
                        <div className="cnd-doc-actions">
                          {selected ? (
                            <>
                              <span className="cnd-doc-selected">{selected.name} ({fileSizeLabel(selected.size)})</span>
                              <button
                                type="button"
                                className="cnd-icon-btn cnd-doc-delete"
                                onClick={() => setDocFiles((current) => ({ ...current, [key]: null }))}
                                aria-label={`Remove selected ${label}`}
                                title="Remove selected file"
                              >
                                <Trash2 size={16} />
                              </button>
                            </>
                          ) : null}
                          {!doc.file_url && !selected ? (
                            <label className="cnd-btn cnd-btn-ghost cnd-btn-sm cnd-upload-label">
                              <Upload size={15} /> Choose file
                              <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(event) => chooseDocument(key, event)} />
                            </label>
                          ) : null}
                          {doc.file_url ? (
                            <>
                              <button type="button" className="cnd-icon-btn cnd-doc-delete" onClick={() => setRemoveDoc(key)} aria-label={`Remove ${label}`}>
                                <Trash2 size={16} />
                              </button>
                              <button type="button" className="cnd-btn cnd-btn-ghost cnd-btn-sm" onClick={() => setViewDocument({ ...doc, label })}>
                                <Eye size={15} /> View
                              </button>
                            </>
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
                <InlineNote>Accepted formats: PDF, JPG, JPEG and PNG. Maximum size 5 MB per document.</InlineNote>
              </SectionCard>
              <SectionCard title="Additional photos" subtitle="Add up to four more photos to your profile" icon={Camera}>
                {additionalPhotoError ? <InlineNote tone="error">{additionalPhotoError}</InlineNote> : null}
                {profile.additional_photo_urls?.length ? (
                  <div className="cnd-body-photo-grid">
                    {profile.additional_photo_urls.map((url, index) => (
                      <div className="cnd-body-photo-card" key={`${url}-${index}`}>
                        <div className="cnd-body-photo-preview"><img src={url} alt={`Additional profile photo ${index + 1}`} /></div>
                        <strong>Additional photo {index + 1}</strong>
                        <span>Uploaded</span>
                      </div>
                    ))}
                  </div>
                ) : <InlineNote>No additional photos uploaded yet.</InlineNote>}
                <div className="cnd-doc-footer">
                  <label className="cnd-btn cnd-btn-ghost cnd-upload-label">
                    <Upload size={15} /> Choose photos
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      multiple
                      onChange={chooseAdditionalPhotos}
                    />
                  </label>
                  <span>{additionalPhotoFiles.length ? `${additionalPhotoFiles.length} photo(s) selected` : `${profile.additional_photo_urls?.length || 0} of 4 photos uploaded`}</span>
                  <button type="button" className="cnd-btn cnd-btn-primary" onClick={uploadAdditionalPhotos} disabled={additionalPhotoBusy || !additionalPhotoFiles.length}>
                    {additionalPhotoBusy ? <Loader2 size={16} className="cnd-spin" /> : <Upload size={16} />} Upload photos
                  </button>
                </div>
                <InlineNote>Accepted formats: JPG, PNG and WebP. Maximum size 5 MB per photo.</InlineNote>
              </SectionCard>
              <div className="cnd-preference-note">
                <Heart size={20} />
                <div>
                  <strong>Partner preferences</strong>
                  <p>These preferences directly influence your match score. Leave a field blank for no preference.</p>
                </div>
              </div>
              {preferenceNotice ? <InlineNote tone="error">{preferenceNotice}</InlineNote> : null}
              {PREFERENCE_SECTIONS.map((section) => {
                const Icon = section.icon;
                return (
                  <SectionCard key={`edit-${section.key}`} title={section.title} subtitle={section.subtitle} icon={Icon}>
                    <div className="cnd-form-grid">
                      {section.fields.map((field) => renderField(field, preferenceForm, updatePreference, preferenceErrors))}
                    </div>
                  </SectionCard>
                );
              })}
              <div className="cnd-form-actions">
                <button type="button" className="cnd-btn cnd-btn-light" onClick={savePartnerPreferences} disabled={savingPreference}>
                  {savingPreference ? <Loader2 size={16} className="cnd-spin" /> : <Heart size={16} />} Save preferences
                </button>
              </div>
              <div className="cnd-form-sticky">
                <span>Save profile details after making changes.</span>
                <button type="submit" className="cnd-btn cnd-btn-primary" disabled={saving}>
                  {saving ? <Loader2 size={16} className="cnd-spin" /> : <Save size={16} />} Update profile
                </button>
              </div>
            </form>
          ) : null}

          {profileComplete && activeTab === "preferences" ? (
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

          {activeTab === "documents" ? (
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
                          {selected ? (
                            <>
                              <span className="cnd-doc-selected">{selected.name} ({fileSizeLabel(selected.size)})</span>
                              <button
                                type="button"
                                className="cnd-icon-btn cnd-doc-delete"
                                onClick={() => setDocFiles((current) => ({ ...current, [key]: null }))}
                                aria-label={`Remove selected ${label}`}
                                title="Remove selected file"
                              >
                                <Trash2 size={16} />
                              </button>
                            </>
                          ) : null}
                          {!doc.file_url && !selected ? (
                            <label className="cnd-btn cnd-btn-ghost cnd-btn-sm cnd-upload-label">
                              <Upload size={15} /> Choose file
                              <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(event) => chooseDocument(key, event)}
                              />
                            </label>
                          ) : null}
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
