import { useEffect, useMemo, useRef, useState } from "react";
import {
  BadgeCheck, Briefcase, Camera, CheckCircle2, FileText, GraduationCap,
  Eye, Heart, Loader2, MapPin, Save, Settings2, ShieldCheck, Sparkles,
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
  uploadCandidateAdditionalPhotos, uploadCandidateDocuments, uploadCandidatePhoto,
  uploadCandidateBodyPhotos,
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
      { name: "source_of_information", label: "Source of information", required: true, placeholder: "WhatsApp, friend, website..." },
      { name: "religion", label: "Religion", required: true, readOnly: true },
      { name: "caste", label: "Community", required: true, placeholder: "Community" },
      { name: "sub_caste", label: "Sub community", placeholder: "Sub community" },
      { name: "marital_status", label: "Marital status", required: true, placeholder: "Never married, divorced..." },
      { name: "birth_date", label: "Date of birth", type: "date", required: true },
      { name: "height", label: "Height", required: true, placeholder: "5 ft 6 in" },
      { name: "weight", label: "Weight", required: true, placeholder: "60 kg" },
      { name: "having_specs_lenses", label: "Specs / lenses", type: "select", options: [["", "Select"], ["None", "None"], ["Specs", "Specs"], ["Lenses", "Lenses"], ["Both", "Both"]] },
      { name: "blood_group", label: "Blood group", required: true, placeholder: "B+" },
      { name: "mother_tongue", label: "Mother tongue", required: true, placeholder: "Marathi" },
      { name: "language", label: "Other languages known", placeholder: "Hindi, English..." },
      { name: "special_cases", label: "Special cases", placeholder: "None / details" },
      { name: "about", label: "About you", type: "textarea", rows: 5, full: true, placeholder: "Describe yourself, your values, interests and what you are looking for." },
      { name: "address", label: "Current address", type: "textarea", required: true, rows: 3, full: true, placeholder: "Current residential address" },
      { name: "permanent_address", label: "Permanent address", type: "textarea", required: true, rows: 3, full: true, placeholder: "Permanent address" },
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
      { name: "horoscope_caste", label: "Horoscope community", placeholder: "Community" },
      { name: "horoscope_sub_caste", label: "Horoscope sub community", placeholder: "Sub community" },
      { name: "horoscope_dob", label: "Horoscope date of birth", type: "date" },
      { name: "rashi", label: "Rashi", placeholder: "Rashi" },
      { name: "nakshatra", label: "Nakshatra", placeholder: "Nakshatra" },
      { name: "charan", label: "Charan", placeholder: "Charan" },
      { name: "gan", label: "Gan", placeholder: "Gan" },
      { name: "gotra", label: "Gotra", placeholder: "Gotra" },
      { name: "nadi", label: "Nadi", placeholder: "Nadi" },
      { name: "match_patrika", label: "Match patrika", placeholder: "Details" },
      { name: "kundali", label: "Kundali", placeholder: "Details" },
    ],
  },
  {
    key: "education",
    title: "Educational details",
    subtitle: "Your qualifications and institutions",
    icon: GraduationCap,
    fields: [
      { name: "education", label: "Education", required: true, placeholder: "Highest education" },
      { name: "degree", label: "Degree / diploma", required: true, placeholder: "Degree name" },
      { name: "degree_2", label: "Additional degree", placeholder: "Second degree" },
      { name: "specialisation", label: "Specialisation 1", placeholder: "Specialisation" },
      { name: "specialisation_2", label: "Specialisation 2", placeholder: "Additional specialisation" },
      { name: "college", label: "University / college", placeholder: "Institute name" },
      { name: "city_of_education", label: "City of education", placeholder: "City" },
      { name: "additional_education", label: "Additional education 1", placeholder: "Certifications or courses" },
      { name: "additional_education_2", label: "Additional education 2", placeholder: "Additional qualification" },
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
    key: "hobbies",
    title: "Hobbies and interests",
    subtitle: "Lifestyle and activities",
    icon: Sparkles,
    fields: [
      { name: "hobbies", label: "Hobbies", required: true, type: "textarea", rows: 2, full: true, placeholder: "Reading, travelling, music..." },
      { name: "extra_curricular", label: "Extra-curricular", type: "textarea", rows: 2, full: true, placeholder: "Activities you enjoy" },
      { name: "achievements", label: "Achievements", type: "textarea", rows: 2, full: true, placeholder: "Notable achievements" },
      { name: "sports", label: "Sports and fitness", placeholder: "Cricket, gym, yoga..." },
      { name: "cooking_skills", label: "Cooking skills", placeholder: "Basic, expert..." },
      { name: "diet", label: "Diet", placeholder: "Vegetarian / non-vegetarian" },
      { name: "smoking", label: "Smoking", type: "select", options: [["", "Select"], ["None", "None"], ["Occasionally", "Occasionally"], ["Yes", "Yes"]] },
      { name: "smoking_frequency", label: "Smoking frequency", placeholder: "Frequency" },
      { name: "drinking", label: "Drinking", type: "select", options: [["", "Select"], ["None", "None"], ["Occasionally", "Occasionally"], ["Yes", "Yes"]] },
      { name: "drinking_frequency", label: "Drinking frequency", placeholder: "Frequency" },
      { name: "partying", label: "Partying", placeholder: "Details" },
      { name: "active_on_social_sites", label: "Active on social sites?", type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "instagram", label: "Instagram", placeholder: "Username or URL" },
      { name: "facebook", label: "Facebook", placeholder: "Username or URL" },
    ],
  },
  {
    key: "medical",
    title: "Medical details",
    subtitle: "Share medical information if applicable",
    icon: ShieldCheck,
    fields: [
      { name: "has_medical_history", label: "Any medical history?", required: true, type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "personal_medical_history", label: "Personal medical history", type: "textarea", rows: 3, full: true, placeholder: "Add details if applicable" },
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
      { name: "father_contact", label: "Father's contact number", placeholder: "Contact number" },
      { name: "father_occupation", label: "Father's occupation", placeholder: "Occupation" },
      { name: "father_native_place", label: "Father's native place", placeholder: "Native place" },
      { name: "mother_name", label: "Mother's name", required: true, placeholder: "Mother's name" },
      { name: "mother_alive", label: "Mother alive", required: true, type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "mother_contact", label: "Mother's contact number", placeholder: "Contact number" },
      { name: "mother_occupation", label: "Mother's occupation", placeholder: "Occupation" },
      { name: "mother_native_place", label: "Mother's native place", placeholder: "Native place" },
      { name: "brothers", label: "Number of brothers", required: true, type: "number", min: 0, max: 20 },
      { name: "brothers_married", label: "Brothers married", type: "number", min: 0, max: 20 },
      { name: "sisters", label: "Number of sisters", required: true, type: "number", min: 0, max: 20 },
      { name: "sisters_married", label: "Sisters married", type: "number", min: 0, max: 20 },
      { name: "parents_intercaste_marriage", label: "Parents inter-caste marriage?", type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "parents_different_caste", label: "If yes, which different caste", placeholder: "Caste details" },
      { name: "family_values", label: "Family values", placeholder: "Traditional, moderate..." },
      { name: "family_financial_status", label: "Financial status", placeholder: "Middle class" },
      { name: "family_annual_income", label: "Family income (annual)", placeholder: "Annual income" },
      { name: "home_status", label: "Home status", placeholder: "Owned / rented" },
      { name: "owned_real_estate", label: "Owned real estate", placeholder: "Details" },
      { name: "has_vehicle", label: "Has vehicle?", type: "select", options: [["", "Select"], ["Yes", "Yes"], ["No", "No"]] },
      { name: "vehicle_type", label: "Vehicle type", placeholder: "Car, bike..." },
      { name: "other_investments", label: "Other investments", placeholder: "Details" },
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
      { name: "reference_contact", label: "Contact", required: true, placeholder: "Mobile number" },
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
  form.religion = "Jain";
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
      setProfile(result?.profile || null);
      setAccount(result?.user || cached?.user || null);
      setForm(buildForm(result?.profile));
      setPreferenceForm(buildPreferenceForm(result?.profile?.preference));
      const requestedTab = new URLSearchParams(window.location.search).get("tab");
      const complete = Number(result?.profile?.profile_completion || 0) >= 100;
      setTab(complete
        ? tabFromUrl()
        : ["overview", "documents"].includes(requestedTab)
          ? requestedTab
          : "edit");
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
  const profileComplete = completion >= 100;
  const conditionalRequiredFields = new Set([
    ...(["Shwetambar", "Digambar"].includes(form.caste) ? ["sub_caste"] : []),
    ...(form.father_alive === "Yes" ? ["father_occupation", "father_contact"] : []),
    ...(form.mother_alive === "Yes" ? ["mother_occupation", "mother_contact"] : []),
    ...(form.has_medical_history === "Yes" ? ["personal_medical_history", "current_medical_problems", "medications"] : []),
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
      setTab(Number(result?.profile?.profile_completion || 0) >= 100 ? "overview" : "edit");
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
      setProfile(result?.profile || profile);
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
      setProfile(result?.profile || profile);
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
                    <div className="cnd-form-grid">
                      {section.fields.map((field) => renderField({
                        ...field,
                        required: field.required || conditionalRequiredFields.has(field.name),
                      }, form, updateField, fieldErrors))}
                    </div>
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
                          {selected ? <span className="cnd-doc-selected">{selected.name} ({fileSizeLabel(selected.size)})</span> : null}
                          <label className="cnd-btn cnd-btn-ghost cnd-btn-sm cnd-upload-label">
                            <Upload size={15} /> {doc.file_url ? "Replace" : "Choose file"}
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(event) => chooseDocument(key, event)} />
                          </label>
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
