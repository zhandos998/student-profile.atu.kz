import DangerButton from "@/Components/DangerButton";
import Checkbox from "@/Components/Checkbox";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";

const valueOrEmpty = (value) => value ?? "";

const optionLabel = (options, value) =>
    options.find((option) => option.value === value)?.label ?? value;

const optionLabels = (options, values = []) =>
    values
        .map((value) => optionLabel(options, value))
        .filter(Boolean)
        .join(", ");

const yesNo = (value) => (value ? "Да" : "Нет");

const primaryActionClass =
    "inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2f5192] disabled:opacity-50";

const revisionActionClass =
    "inline-flex items-center justify-center rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-50";

const dispensaryAccountingOptions = [
    { value: "1", label: "Да" },
    { value: "0", label: "Нет" },
];

function Section({
    title,
    children,
    actions = null,
    hidden = false,
    tone = "default",
}) {
    if (hidden) {
        return null;
    }

    const danger = tone === "danger";

    return (
        <section
            className={`overflow-hidden rounded-lg border shadow-sm ${
                danger
                    ? "border-red-200 bg-red-50/80"
                    : "border-gray-200 bg-white"
            }`}
        >
            <div
                className={`border-b px-6 py-4 ${
                    danger
                        ? "border-red-200 bg-red-100"
                        : "border-[#dbe5f6] bg-[#edf3ff]"
                }`}
            >
                <h3
                    className={`text-base font-semibold ${
                        danger ? "text-red-800" : "text-[#274f93]"
                    }`}
                >
                    {title}
                </h3>
            </div>
            <div className="p-6">
                {children}
                {actions && (
                    <div
                        className={`mt-6 flex items-center justify-end gap-4 border-t pt-4 ${
                            danger ? "border-red-200" : "border-gray-100"
                        }`}
                    >
                        {actions}
                    </div>
                )}
            </div>
        </section>
    );
}

function Field({ label, error, children, className = "" }) {
    return (
        <div className={className}>
            <InputLabel value={label} />
            <div className="mt-1">{children}</div>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function DisplayField({ label, value, className = "" }) {
    return (
        <div className={className}>
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                {label}
            </p>
            <p className="mt-1 whitespace-pre-wrap text-sm font-medium text-gray-900">
                {value === null || value === undefined || value === ""
                    ? "Не указано"
                    : value}
            </p>
        </div>
    );
}

function SelectInput({
    value,
    onChange,
    options,
    placeholder = "Не указано",
    disabled = false,
}) {
    return (
        <select
            value={value}
            onChange={onChange}
            disabled={disabled}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8] disabled:cursor-not-allowed disabled:bg-gray-50"
        >
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

function TextAreaInput({ value, onChange, rows = 3, disabled = false }) {
    return (
        <textarea
            value={value}
            onChange={onChange}
            rows={rows}
            disabled={disabled}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8] disabled:cursor-not-allowed disabled:bg-gray-50"
        />
    );
}

function BooleanCheckbox({ checked, label, onChange }) {
    return (
        <label className="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-700">
            <Checkbox checked={checked} onChange={onChange} />
            <span>{label}</span>
        </label>
    );
}

function CheckboxGroup({ value = [], options, onChange }) {
    const selected = value ?? [];

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {options.map((option) => (
                <label
                    key={option.value}
                    className="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-700"
                >
                    <Checkbox
                        checked={selected.includes(option.value)}
                        onChange={(event) => {
                            if (event.target.checked) {
                                onChange([...selected, option.value]);

                                return;
                            }

                            onChange(
                                selected.filter(
                                    (item) => item !== option.value,
                                ),
                            );
                        }}
                    />
                    <span>{option.label}</span>
                </label>
            ))}
        </div>
    );
}

function FileLink({ href, label }) {
    return href ? (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className="mt-2 inline-flex text-sm font-medium text-[#355da8] hover:text-[#2f5192]"
        >
            {label}
        </a>
    ) : null;
}

function FileSize({ size }) {
    if (!size) {
        return null;
    }

    const kilobytes = Math.max(1, Math.round(size / 1024));

    return <span>{kilobytes} KB</span>;
}

export default function Edit({
    profile,
    academicProfile,
    healthPassport = {},
    achievements,
    portfolioItems,
    options,
    availableGroups = [],
    profileStatusOptions = [],
    isManagedProfile = false,
    canEditProfile = true,
    canEditHealthPassport = false,
    healthPassportUpdateUrl = null,
    targetUser = null,
}) {
    const [achievementFileKey, setAchievementFileKey] = useState(0);
    const [portfolioFileKey, setPortfolioFileKey] = useState(0);
    const [healthPassportFileKey, setHealthPassportFileKey] = useState(0);
    const [socialSupport, setSocialSupport] = useState({
        is_orphan: Boolean(profile.is_orphan),
        is_half_orphan: Boolean(profile.is_half_orphan),
        is_incomplete_family: Boolean(profile.is_incomplete_family),
        is_large_family: Boolean(profile.is_large_family),
        is_low_income: Boolean(profile.is_low_income),
    });
    const [legalRepresentative, setLegalRepresentative] = useState(
        valueOrEmpty(profile.legal_representative),
    );
    const [halfOrphanType, setHalfOrphanType] = useState(
        valueOrEmpty(profile.half_orphan_type),
    );

    const [profileData, setProfileData] = useState({
        full_name: valueOrEmpty(profile.full_name),
        birth_date: valueOrEmpty(profile.birth_date),
        study_form: valueOrEmpty(profile.study_form),
        nationality: valueOrEmpty(profile.nationality),
        citizenship: valueOrEmpty(profile.citizenship),
        student_status: valueOrEmpty(profile.student_status),
        departure_reason: valueOrEmpty(profile.departure_reason),
        departure_reason_other: valueOrEmpty(profile.departure_reason_other),
        departed_at: valueOrEmpty(profile.departed_at),
        military_department_status: valueOrEmpty(
            profile.military_department_status,
        ),
        military_department_place: valueOrEmpty(
            profile.military_department_place,
        ),
        photo: null,
        iin: valueOrEmpty(profile.iin),
        identity_document_number: valueOrEmpty(
            profile.identity_document_number,
        ),
        identity_card: null,
        gender: valueOrEmpty(profile.gender),
        faculty: valueOrEmpty(profile.faculty),
        student_group_id: valueOrEmpty(profile.student_group_id),
        group_name: valueOrEmpty(profile.group_name),
        specialty: valueOrEmpty(profile.specialty),
        course: valueOrEmpty(profile.course),
        admission_year: valueOrEmpty(profile.admission_year),
        marital_status: valueOrEmpty(profile.marital_status),
        disability_group: valueOrEmpty(profile.disability_group),
        disabled_parent_group: valueOrEmpty(profile.disabled_parent_group),
        disabled_sibling_group: valueOrEmpty(profile.disabled_sibling_group),
        benefits: profile.benefits ?? [],
        social_support_need_status: valueOrEmpty(
            profile.social_support_need_status,
        ),
        social_support_need_details: valueOrEmpty(
            profile.social_support_need_details,
        ),
        special_educational_needs: valueOrEmpty(
            profile.special_educational_needs,
        ),
        stay_address: valueOrEmpty(profile.stay_address),
        residence_address: valueOrEmpty(profile.residence_address),
        contact_details: valueOrEmpty(profile.contact_details),
        personal_email: valueOrEmpty(profile.personal_email),
        parent_guardian_contacts: valueOrEmpty(
            profile.parent_guardian_contacts,
        ),
        foreign_student_country: valueOrEmpty(profile.foreign_student_country),
        kandas_country: valueOrEmpty(profile.kandas_country),
        dormitory_details: valueOrEmpty(profile.dormitory_details),
        relatives_living_details: valueOrEmpty(
            profile.relatives_living_details,
        ),
        rental_housing_details: valueOrEmpty(profile.rental_housing_details),
        education_language: valueOrEmpty(academicProfile.education_language),
        gpa: valueOrEmpty(academicProfile.gpa),
        final_grades: valueOrEmpty(academicProfile.final_grades),
        current_performance: valueOrEmpty(academicProfile.current_performance),
        academic_debt: valueOrEmpty(academicProfile.academic_debt),
    });
    const [profileErrors, setProfileErrors] = useState({});
    const [profileProcessing, setProfileProcessing] = useState(false);
    const [profileRecentlySuccessful, setProfileRecentlySuccessful] =
        useState(false);
    const [blockReviewComments, setBlockReviewComments] = useState({
        social: "",
        academic: "",
    });
    const [blockReviewErrors, setBlockReviewErrors] = useState({});
    const [blockReviewProcessing, setBlockReviewProcessing] = useState(false);

    const profileForm = {
        data: profileData,
        errors: profileErrors,
        processing: profileProcessing,
        recentlySuccessful: profileRecentlySuccessful,
        setData: (fieldOrData, value) => {
            if (typeof fieldOrData === "string") {
                setProfileData((current) => ({
                    ...current,
                    [fieldOrData]: value,
                }));

                return;
            }

            if (typeof fieldOrData === "function") {
                setProfileData((current) => fieldOrData(current));

                return;
            }

            setProfileData(fieldOrData);
        },
    };
    const visibleGroupOptions = availableGroups.filter(
        (group) =>
            !profileData.faculty ||
            !group.faculty ||
            group.faculty === profileData.faculty,
    );

    const setFaculty = (faculty) => {
        profileForm.setData((current) => {
            const selectedGroup = availableGroups.find(
                (group) =>
                    String(group.value) === String(current.student_group_id),
            );

            return {
                ...current,
                faculty,
                student_group_id:
                    selectedGroup?.faculty && selectedGroup.faculty !== faculty
                        ? ""
                        : current.student_group_id,
                group_name:
                    selectedGroup?.faculty && selectedGroup.faculty !== faculty
                        ? ""
                        : current.group_name,
            };
        });
    };

    const setStudentGroupId = (studentGroupId) => {
        const selectedGroup = availableGroups.find(
            (group) => String(group.value) === String(studentGroupId),
        );

        profileForm.setData((current) => ({
            ...current,
            student_group_id: studentGroupId,
            group_name: selectedGroup?.name || selectedGroup?.label || "",
            faculty: selectedGroup?.faculty || current.faculty,
        }));
    };

    const setSocialSupportFlag = (field, checked) => {
        setSocialSupport((current) => ({
            ...current,
            [field]: checked,
        }));

        if (field === "is_orphan" && !checked) {
            setLegalRepresentative("");
        }

        if (field === "is_half_orphan" && !checked) {
            setHalfOrphanType("");
        }
    };

    const setMilitaryDepartmentStatus = (value) => {
        profileForm.setData((current) => ({
            ...current,
            military_department_status: value,
            military_department_place:
                value === "studying" ? current.military_department_place : "",
        }));
    };

    const setSocialSupportNeedStatus = (value) => {
        profileForm.setData((current) => ({
            ...current,
            social_support_need_status: value,
            social_support_need_details:
                value === "needs" ? current.social_support_need_details : "",
        }));
    };

    const achievementForm = useForm({
        activity_type: "olympiad",
        title: "",
        level: "atu",
        result: "participant",
        description: "",
        document: null,
    });

    const portfolioForm = useForm({
        item_type: "certificate",
        title: "",
        file: null,
    });
    const statusForm = useForm({
        profile_status: "verified",
        revision_comment: "",
    });
    const healthPassportForm = useForm({
        fluorography_date: healthPassport.fluorography_date ?? "",
        fluorography_image: null,
        dispensary_accounting: healthPassport.dispensary_accounting ?? "",
        diagnosis: healthPassport.diagnosis ?? "",
        disability_group: healthPassport.disability_group ?? "",
        psychological_diagnosis: healthPassport.psychological_diagnosis ?? "",
        pregnancy: healthPassport.pregnancy ?? "",
    });
    const targetUserId = targetUser?.id;
    const profileUpdateUrl = isManagedProfile
        ? route("student-profiles.update", targetUserId)
        : route("student-profile.update");
    const profileStatusUpdateUrl = isManagedProfile
        ? route("student-profiles.status.update", targetUserId)
        : null;
    const blockReviewUpdateUrl = isManagedProfile
        ? route("student-profiles.review-block.update", targetUserId)
        : null;
    const achievementStoreUrl = isManagedProfile
        ? route("student-profiles.achievements.store", targetUserId)
        : route("student-profile.achievements.store");
    const portfolioStoreUrl = isManagedProfile
        ? route("student-profiles.portfolio.store", targetUserId)
        : route("student-profile.portfolio.store");
    const achievementDestroyUrl = (achievementId) =>
        isManagedProfile
            ? route("student-profiles.achievements.destroy", [
                  targetUserId,
                  achievementId,
              ])
            : route("student-profile.achievements.destroy", achievementId);
    const portfolioDestroyUrl = (portfolioItemId) =>
        isManagedProfile
            ? route("student-profiles.portfolio.destroy", [
                  targetUserId,
                  portfolioItemId,
              ])
            : route("student-profile.portfolio.destroy", portfolioItemId);

    const submitProfile = (event) => {
        event.preventDefault();

        router.post(
            profileUpdateUrl,
            {
                ...profileData,
                group_name: profileData.student_group_id
                    ? ""
                    : profileData.group_name,
                is_orphan: socialSupport.is_orphan ? "1" : "0",
                legal_representative: socialSupport.is_orphan
                    ? legalRepresentative
                    : "",
                is_half_orphan: socialSupport.is_half_orphan ? "1" : "0",
                half_orphan_type: socialSupport.is_half_orphan
                    ? halfOrphanType
                    : "",
                is_incomplete_family: socialSupport.is_incomplete_family
                    ? "1"
                    : "0",
                is_large_family: socialSupport.is_large_family ? "1" : "0",
                is_low_income: socialSupport.is_low_income ? "1" : "0",
                benefits: profileData.benefits ?? [],
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => {
                    setProfileProcessing(true);
                    setProfileRecentlySuccessful(false);
                },
                onError: (errors) => {
                    setProfileErrors(errors);
                },
                onSuccess: () => {
                    setProfileErrors({});
                    setProfileRecentlySuccessful(true);
                    setProfileData((current) => ({
                        ...current,
                        photo: null,
                        identity_card: null,
                    }));
                    window.setTimeout(
                        () => setProfileRecentlySuccessful(false),
                        2000,
                    );
                },
                onFinish: () => {
                    setProfileProcessing(false);
                },
            },
        );
    };

    const updateProfileStatus = (profileStatus) => {
        statusForm
            .transform((data) => ({
                ...data,
                profile_status: profileStatus,
                revision_comment:
                    profileStatus === "needs_revision"
                        ? data.revision_comment
                        : "",
            }))
            .post(profileStatusUpdateUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    statusForm.reset("revision_comment");
                },
            });
    };

    const updateBlockReview = (block, reviewStatus) => {
        router.post(
            blockReviewUpdateUrl,
            {
                block,
                review_status: reviewStatus,
                review_comment:
                    reviewStatus === "needs_revision"
                        ? blockReviewComments[block]
                        : "",
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setBlockReviewProcessing(true);
                    setBlockReviewErrors({});
                },
                onError: (errors) => setBlockReviewErrors(errors),
                onSuccess: () => {
                    setBlockReviewErrors({});
                    setBlockReviewComments((current) => ({
                        ...current,
                        [block]: "",
                    }));
                },
                onFinish: () => setBlockReviewProcessing(false),
            },
        );
    };

    const submitAchievement = (event) => {
        event.preventDefault();

        achievementForm.post(achievementStoreUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                achievementForm.reset();
                setAchievementFileKey((key) => key + 1);
            },
        });
    };

    const submitPortfolio = (event) => {
        event.preventDefault();

        portfolioForm.post(portfolioStoreUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                portfolioForm.reset();
                setPortfolioFileKey((key) => key + 1);
            },
        });
    };

    const submitHealthPassport = (event) => {
        event.preventDefault();

        healthPassportForm.post(healthPassportUpdateUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                healthPassportForm.setData("fluorography_image", null);
                setHealthPassportFileKey((key) => key + 1);
            },
        });
    };

    const renderSectionSave = () => (
        <>
            {profileForm.recentlySuccessful && (
                <p className="text-sm text-gray-600">Сохранено</p>
            )}
            <PrimaryButton type="submit" disabled={profileForm.processing}>
                Сохранить
            </PrimaryButton>
        </>
    );

    const currentStatusLabel =
        profile.profile_status_label ||
        profileStatusOptions.find(
            (option) => option.value === profile.profile_status,
        )?.label ||
        profile.profile_status ||
        "Не заполнена";

    const renderBlockReview = ({
        block,
        statusLabel,
        reviewedAtDisplay,
        comment,
    }) => (
        <div className="mb-5 rounded-md bg-[#f4f7fc] px-4 py-3 ring-1 ring-[#dbe5f6]">
            <div className="flex flex-wrap items-center gap-3">
                <span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#355da8] ring-1 ring-[#dbe5f6]">
                    {statusLabel}
                </span>
                {reviewedAtDisplay && (
                    <span className="text-xs text-gray-500">
                        Проверено: {reviewedAtDisplay}
                    </span>
                )}
            </div>

            {comment && (
                <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-100">
                    {comment}
                </p>
            )}

            {isManagedProfile && (
                <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-start">
                    <div>
                        <textarea
                            value={blockReviewComments[block]}
                            onChange={(event) =>
                                setBlockReviewComments((current) => ({
                                    ...current,
                                    [block]: event.target.value,
                                }))
                            }
                            rows={2}
                            placeholder="Комментарий для исправления"
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                        />
                        <InputError
                            message={blockReviewErrors.review_comment}
                            className="mt-2"
                        />
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <button
                            type="button"
                            onClick={() =>
                                updateBlockReview(block, "needs_revision")
                            }
                            disabled={blockReviewProcessing}
                            className={revisionActionClass}
                        >
                            Вернуть
                        </button>
                        <button
                            type="button"
                            onClick={() => updateBlockReview(block, "verified")}
                            disabled={blockReviewProcessing}
                            className={primaryActionClass}
                        >
                            Подтвердить
                        </button>
                    </div>
                </div>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {isManagedProfile
                                ? `Портрет студента: ${targetUser.name}`
                                : "Портрет студента"}
                        </h2>
                        {isManagedProfile && (
                            <p className="mt-1 text-sm text-gray-500">
                                {targetUser.email}
                            </p>
                        )}
                    </div>
                    {isManagedProfile && (
                        <Link
                            href={route("student-profiles.index")}
                            className="text-sm font-medium text-[#355da8] hover:text-[#2f5192]"
                        >
                            К списку портретов
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Портрет студента" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {canEditProfile && (
                    <section className="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                            <h3 className="text-base font-semibold text-[#274f93]">
                                Статус анкеты
                            </h3>
                        </div>

                        <div className="flex flex-col gap-4 p-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="rounded-full bg-[#f4f7fc] px-3 py-1 text-sm font-semibold text-[#355da8] ring-1 ring-[#dbe5f6]">
                                        {currentStatusLabel}
                                    </span>
                                    {profile.submitted_at_display && (
                                        <span className="text-sm text-gray-500">
                                            Отправлена:{" "}
                                            {profile.submitted_at_display}
                                        </span>
                                    )}
                                    {profile.verified_at_display && (
                                        <span className="text-sm text-gray-500">
                                            Проверена:{" "}
                                            {profile.verified_at_display}
                                        </span>
                                    )}
                                </div>
                                {profile.revision_comment && (
                                    <p className="mt-3 max-w-3xl rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-100">
                                        {profile.revision_comment}
                                    </p>
                                )}
                                <InputError
                                    message={profileErrors.profile_status}
                                    className="mt-2"
                                />
                            </div>

                            {isManagedProfile && (
                                <div className="w-full max-w-md space-y-3">
                                    <textarea
                                        value={statusForm.data.revision_comment}
                                        onChange={(event) =>
                                            statusForm.setData(
                                                "revision_comment",
                                                event.target.value,
                                            )
                                        }
                                        rows={3}
                                        placeholder="Комментарий для исправления"
                                        className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                                    />
                                    <InputError
                                        message={
                                            statusForm.errors.revision_comment
                                        }
                                    />
                                    <div className="flex flex-wrap justify-end gap-3">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                updateProfileStatus(
                                                    "needs_revision",
                                                )
                                            }
                                            disabled={statusForm.processing}
                                            className={revisionActionClass}
                                        >
                                            Вернуть на исправление
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                updateProfileStatus("verified")
                                            }
                                            disabled={statusForm.processing}
                                            className={primaryActionClass}
                                        >
                                            Проверить
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                    )}

                    {!canEditProfile && (
                        <section className="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                                <h3 className="text-base font-semibold text-[#274f93]">
                                    Карточка студента
                                </h3>
                            </div>

                            <div className="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                                <DisplayField
                                    label="ФИО"
                                    value={profile.full_name || targetUser?.name}
                                />
                                <DisplayField
                                    label="Email"
                                    value={targetUser?.email}
                                />
                                <DisplayField
                                    label="Дата рождения"
                                    value={profile.birth_date}
                                />
                                <DisplayField
                                    label="Форма обучения"
                                    value={profile.study_form}
                                />
                                <DisplayField
                                    label="Национальность"
                                    value={profile.nationality}
                                />
                                <DisplayField
                                    label="Гражданство"
                                    value={optionLabel(
                                        options.citizenships,
                                        profile.citizenship,
                                    )}
                                />
                                <DisplayField label="ИИН" value={profile.iin} />
                                <DisplayField
                                    label="№ удостоверения личности"
                                    value={profile.identity_document_number}
                                />
                                <DisplayField
                                    label="Пол"
                                    value={optionLabel(
                                        options.genders,
                                        profile.gender,
                                    )}
                                />
                                <DisplayField
                                    label="Факультет"
                                    value={profile.faculty}
                                />
                                <DisplayField
                                    label="Группа"
                                    value={profile.group_name}
                                />
                                <DisplayField
                                    label="Специальность"
                                    value={profile.specialty}
                                />
                                <DisplayField label="Курс" value={profile.course} />
                                <DisplayField
                                    label="Год поступления"
                                    value={profile.admission_year}
                                />
                                <DisplayField
                                    label="Контактные данные"
                                    value={profile.contact_details}
                                    className="md:col-span-2 xl:col-span-3"
                                />
                            </div>
                        </section>
                    )}

                    {!canEditProfile && (
                        <section className="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                                <h3 className="text-base font-semibold text-[#274f93]">
                                    Социальный статус
                                </h3>
                            </div>

                            <div className="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                                <DisplayField
                                    label="Инвалид"
                                    value={optionLabel(
                                        options.disabilityGroups,
                                        profile.disability_group,
                                    )}
                                />
                                <DisplayField
                                    label="Родитель/ли инвалиды"
                                    value={optionLabel(
                                        options.disabilityGroups,
                                        profile.disabled_parent_group,
                                    )}
                                />
                                <DisplayField
                                    label="Сестра/брат инвалид"
                                    value={optionLabel(
                                        options.disabilityGroups,
                                        profile.disabled_sibling_group,
                                    )}
                                />
                                <DisplayField
                                    label="Сирота"
                                    value={yesNo(profile.is_orphan)}
                                />
                                <DisplayField
                                    label="Законный представитель"
                                    value={profile.legal_representative}
                                />
                                <DisplayField
                                    label="Полусирота"
                                    value={yesNo(profile.is_half_orphan)}
                                />
                                <DisplayField
                                    label="Тип полусироты"
                                    value={optionLabel(
                                        options.halfOrphanTypes,
                                        profile.half_orphan_type,
                                    )}
                                />
                                <DisplayField
                                    label="Неполная семья"
                                    value={yesNo(profile.is_incomplete_family)}
                                />
                                <DisplayField
                                    label="Многодетная семья"
                                    value={yesNo(profile.is_large_family)}
                                />
                                <DisplayField
                                    label="Малообеспеченная семья"
                                    value={yesNo(profile.is_low_income)}
                                />
                            </div>
                        </section>
                    )}

                    {!canEditProfile && (
                        <section className="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                                <h3 className="text-base font-semibold text-[#274f93]">
                                    Социальная поддержка
                                </h3>
                            </div>

                            <div className="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                                <DisplayField
                                    label="Льготы"
                                    value={optionLabels(
                                        options.benefits,
                                        profile.benefits ?? [],
                                    )}
                                    className="md:col-span-2 xl:col-span-3"
                                />
                                <DisplayField
                                    label="Нуждающийся в социальной поддержке"
                                    value={optionLabel(
                                        options.socialSupportNeedStatuses,
                                        profile.social_support_need_status,
                                    )}
                                />
                                <DisplayField
                                    label="В какой поддержке нуждается"
                                    value={
                                        profile.social_support_need_details
                                    }
                                    className="md:col-span-2"
                                />
                                <DisplayField
                                    label="Особые образовательные потребности"
                                    value={profile.special_educational_needs}
                                    className="md:col-span-2 xl:col-span-3"
                                />
                            </div>
                        </section>
                    )}

                    {!canEditProfile && !canEditHealthPassport && (
                        <section className="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                                <h3 className="text-base font-semibold text-[#274f93]">
                                    Паспорт здоровья обучающегося
                                </h3>
                            </div>

                            <div className="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                                <DisplayField
                                    label="Флюорография: дата прохождения"
                                    value={healthPassport.fluorography_date}
                                />
                                <DisplayField
                                    label="Диспансерный учет"
                                    value={
                                        healthPassport.dispensary_accounting ===
                                        ""
                                            ? ""
                                            : healthPassport.dispensary_accounting ===
                                                "1"
                                              ? "Да"
                                              : "Нет"
                                    }
                                />
                                <DisplayField
                                    label="Группа инвалидности"
                                    value={optionLabel(
                                        options.disabilityGroups,
                                        healthPassport.disability_group,
                                    )}
                                />
                                <DisplayField
                                    label="Диагноз"
                                    value={healthPassport.diagnosis}
                                    className="md:col-span-2 xl:col-span-3"
                                />
                                <DisplayField
                                    label="Психологический диагноз"
                                    value={
                                        healthPassport.psychological_diagnosis
                                    }
                                    className="md:col-span-2 xl:col-span-3"
                                />
                                <DisplayField
                                    label="Беременность"
                                    value={healthPassport.pregnancy}
                                    className="md:col-span-2 xl:col-span-3"
                                />
                                {healthPassport.fluorography_image_url && (
                                    <div className="md:col-span-2 xl:col-span-3">
                                        <FileLink
                                            href={
                                                healthPassport.fluorography_image_url
                                            }
                                            label="Открыть снимок флюорографии"
                                        />
                                    </div>
                                )}
                            </div>
                        </section>
                    )}

                    {canEditProfile && (
                    <form
                        onSubmit={submitProfile}
                        className="space-y-6"
                    >
                        <Section
                            title="Карточка студента"
                            actions={renderSectionSave()}
                        >
                            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                <Field
                                    label="ФИО"
                                    error={profileForm.errors.full_name}
                                    className="xl:col-span-2"
                                >
                                    <TextInput
                                        value={profileForm.data.full_name}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "full_name",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Дата рождения"
                                    error={profileForm.errors.birth_date}
                                >
                                    <TextInput
                                        type="date"
                                        value={profileForm.data.birth_date}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "birth_date",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Форма обучения"
                                    error={profileForm.errors.study_form}
                                >
                                    <TextInput
                                        value={profileForm.data.study_form}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "study_form",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Национальность"
                                    error={profileForm.errors.nationality}
                                >
                                    <TextInput
                                        value={profileForm.data.nationality}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "nationality",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Гражданство"
                                    error={profileForm.errors.citizenship}
                                >
                                    <SelectInput
                                        value={profileForm.data.citizenship}
                                        options={options.citizenships}
                                        placeholder="Выберите гражданство"
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "citizenship",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Военная кафедра"
                                    error={
                                        profileForm.errors
                                            .military_department_status
                                    }
                                >
                                    <SelectInput
                                        value={
                                            profileForm.data
                                                .military_department_status
                                        }
                                        options={
                                            options.militaryDepartmentStatuses
                                        }
                                        placeholder="Выберите статус"
                                        onChange={(event) =>
                                            setMilitaryDepartmentStatus(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Где обучается на военной кафедре"
                                    error={
                                        profileForm.errors
                                            .military_department_place
                                    }
                                >
                                    <TextInput
                                        value={
                                            profileForm.data
                                                .military_department_place
                                        }
                                        disabled={
                                            profileForm.data
                                                .military_department_status !==
                                            "studying"
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "military_department_place",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full disabled:cursor-not-allowed disabled:bg-gray-50"
                                    />
                                </Field>

                                <Field
                                    label="Фото"
                                    error={profileForm.errors.photo}
                                >
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png"
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "photo",
                                                event.target.files[0],
                                            )
                                        }
                                        className="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200"
                                    />
                                    <FileLink
                                        href={profile.photo_url}
                                        label="Открыть текущий файл"
                                    />
                                </Field>

                                <Field
                                    label="ИИН"
                                    error={profileForm.errors.iin}
                                >
                                    <TextInput
                                        value={profileForm.data.iin}
                                        maxLength="12"
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "iin",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="№ удостоверения личности"
                                    error={
                                        profileForm.errors
                                            .identity_document_number
                                    }
                                >
                                    <TextInput
                                        value={
                                            profileForm.data
                                                .identity_document_number
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "identity_document_number",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="ID карта"
                                    error={profileForm.errors.identity_card}
                                >
                                    <input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "identity_card",
                                                event.target.files[0],
                                            )
                                        }
                                        className="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200"
                                    />
                                    <FileLink
                                        href={profile.identity_card_url}
                                        label="Открыть текущий файл"
                                    />
                                </Field>

                                <Field
                                    label="Пол"
                                    error={profileForm.errors.gender}
                                >
                                    <SelectInput
                                        value={profileForm.data.gender}
                                        options={options.genders}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "gender",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Факультет"
                                    error={profileForm.errors.faculty}
                                >
                                    <SelectInput
                                        value={profileForm.data.faculty}
                                        options={options.faculties}
                                        placeholder="Выберите факультет"
                                        onChange={(event) =>
                                            setFaculty(event.target.value)
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Группа"
                                    error={
                                        profileForm.errors.student_group_id ||
                                        profileForm.errors.group_name
                                    }
                                >
                                    <SelectInput
                                        value={
                                            profileForm.data.student_group_id
                                        }
                                        options={visibleGroupOptions}
                                        placeholder={
                                            availableGroups.length === 0
                                                ? "Сначала создайте группу"
                                                : "Выберите группу"
                                        }
                                        disabled={availableGroups.length === 0}
                                        onChange={(event) =>
                                            setStudentGroupId(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Специальность"
                                    error={profileForm.errors.specialty}
                                >
                                    <TextInput
                                        value={profileForm.data.specialty}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "specialty",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Курс"
                                    error={profileForm.errors.course}
                                >
                                    <TextInput
                                        type="number"
                                        min="1"
                                        max="8"
                                        value={profileForm.data.course}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "course",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Год поступления"
                                    error={profileForm.errors.admission_year}
                                >
                                    <TextInput
                                        type="number"
                                        min="2000"
                                        value={profileForm.data.admission_year}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "admission_year",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Семейное положение"
                                    error={profileForm.errors.marital_status}
                                >
                                    <SelectInput
                                        value={profileForm.data.marital_status}
                                        options={options.maritalStatuses}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "marital_status",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </Section>

                        <Section
                            title="Социальный статус"
                            actions={renderSectionSave()}
                        >
                            {renderBlockReview({
                                block: "social",
                                statusLabel: profile.social_review_status_label,
                                reviewedAtDisplay:
                                    profile.social_reviewed_at_display,
                                comment: profile.social_review_comment,
                            })}
                            <div className="space-y-6">
                                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                    <Field
                                        label="Инвалид"
                                        error={
                                            profileForm.errors.disability_group
                                        }
                                    >
                                        <SelectInput
                                            value={
                                                profileForm.data
                                                    .disability_group
                                            }
                                            options={options.disabilityGroups}
                                            placeholder="Нет"
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    "disability_group",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Родитель/ли инвалиды"
                                        error={
                                            profileForm.errors
                                                .disabled_parent_group
                                        }
                                    >
                                        <SelectInput
                                            value={
                                                profileForm.data
                                                    .disabled_parent_group
                                            }
                                            options={options.disabilityGroups}
                                            placeholder="Нет"
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    "disabled_parent_group",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Сестра/брат инвалид"
                                        error={
                                            profileForm.errors
                                                .disabled_sibling_group
                                        }
                                    >
                                        <SelectInput
                                            value={
                                                profileForm.data
                                                    .disabled_sibling_group
                                            }
                                            options={options.disabilityGroups}
                                            placeholder="Нет"
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    "disabled_sibling_group",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                            </div>
                        </Section>

                        <Section
                            title="Социальная поддержка"
                            actions={renderSectionSave()}
                        >
                            <div className="space-y-5">
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    <BooleanCheckbox
                                        label="Сирота"
                                        checked={socialSupport.is_orphan}
                                        onChange={(event) => {
                                            setSocialSupportFlag(
                                                "is_orphan",
                                                event.target.checked,
                                            );
                                        }}
                                    />

                                    <BooleanCheckbox
                                        label="Полусирота"
                                        checked={socialSupport.is_half_orphan}
                                        onChange={(event) => {
                                            setSocialSupportFlag(
                                                "is_half_orphan",
                                                event.target.checked,
                                            );
                                        }}
                                    />

                                    <BooleanCheckbox
                                        label="Не полная семья"
                                        checked={
                                            socialSupport.is_incomplete_family
                                        }
                                        onChange={(event) =>
                                            setSocialSupportFlag(
                                                "is_incomplete_family",
                                                event.target.checked,
                                            )
                                        }
                                    />

                                    <BooleanCheckbox
                                        label="Многодетная семья (4 детей и более)"
                                        checked={socialSupport.is_large_family}
                                        onChange={(event) =>
                                            setSocialSupportFlag(
                                                "is_large_family",
                                                event.target.checked,
                                            )
                                        }
                                    />

                                    <BooleanCheckbox
                                        label="Малообеспеченные (ниже МРП на каждого члена семьи)"
                                        checked={socialSupport.is_low_income}
                                        onChange={(event) =>
                                            setSocialSupportFlag(
                                                "is_low_income",
                                                event.target.checked,
                                            )
                                        }
                                    />
                                </div>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        label="Законный представитель"
                                        error={
                                            profileForm.errors
                                                .legal_representative
                                        }
                                    >
                                        <TextInput
                                            value={
                                                socialSupport.is_orphan
                                                    ? legalRepresentative
                                                    : ""
                                            }
                                            disabled={!socialSupport.is_orphan}
                                            onChange={(event) =>
                                                setLegalRepresentative(
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full disabled:cursor-not-allowed disabled:bg-gray-50"
                                        />
                                    </Field>

                                    <Field
                                        label="Тип полусироты"
                                        error={
                                            profileForm.errors.half_orphan_type
                                        }
                                    >
                                        <SelectInput
                                            value={
                                                socialSupport.is_half_orphan
                                                    ? halfOrphanType
                                                    : ""
                                            }
                                            options={options.halfOrphanTypes}
                                            disabled={
                                                !socialSupport.is_half_orphan
                                            }
                                            onChange={(event) =>
                                                setHalfOrphanType(
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Льготы"
                                        error={profileForm.errors.benefits}
                                        className="md:col-span-2 xl:col-span-3"
                                    >
                                        <CheckboxGroup
                                            value={profileForm.data.benefits}
                                            options={options.benefits}
                                            onChange={(value) =>
                                                profileForm.setData(
                                                    "benefits",
                                                    value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Нуждающийся в социальной поддержке"
                                        error={
                                            profileForm.errors
                                                .social_support_need_status
                                        }
                                    >
                                        <SelectInput
                                            value={
                                                profileForm.data
                                                    .social_support_need_status
                                            }
                                            options={
                                                options.socialSupportNeedStatuses
                                            }
                                            placeholder="Выберите статус"
                                            onChange={(event) =>
                                                setSocialSupportNeedStatus(
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="В какой социальной поддержке нуждается"
                                        error={
                                            profileForm.errors
                                                .social_support_need_details
                                        }
                                        className="md:col-span-2"
                                    >
                                        <TextAreaInput
                                            value={
                                                profileForm.data
                                                    .social_support_need_details
                                            }
                                            rows={2}
                                            disabled={
                                                profileForm.data
                                                    .social_support_need_status !==
                                                "needs"
                                            }
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    "social_support_need_details",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                            </div>
                        </Section>

                        <Section
                            title="Контакты и проживание"
                            actions={renderSectionSave()}
                        >
                            <div className="grid gap-5 md:grid-cols-2">
                                <Field
                                    label="Особые образовательные потребности"
                                    error={
                                        profileForm.errors
                                            .special_educational_needs
                                    }
                                >
                                    <TextAreaInput
                                        value={
                                            profileForm.data
                                                .special_educational_needs
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "special_educational_needs",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Контактные данные(сотовый телефон)"
                                    error={profileForm.errors.contact_details}
                                >
                                    <TextAreaInput
                                        value={profileForm.data.contact_details}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "contact_details",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Электронная почта"
                                    error={profileForm.errors.personal_email}
                                >
                                    <TextInput
                                        type="email"
                                        value={profileForm.data.personal_email}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "personal_email",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Контактные данные родителей/опекунов"
                                    error={
                                        profileForm.errors
                                            .parent_guardian_contacts
                                    }
                                >
                                    <TextAreaInput
                                        value={
                                            profileForm.data
                                                .parent_guardian_contacts
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "parent_guardian_contacts",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Адрес пребывания"
                                    error={profileForm.errors.stay_address}
                                >
                                    <TextAreaInput
                                        value={profileForm.data.stay_address}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "stay_address",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Адрес проживания"
                                    error={profileForm.errors.residence_address}
                                >
                                    <TextAreaInput
                                        value={
                                            profileForm.data.residence_address
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "residence_address",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Иностранный студент (указать страну)"
                                    error={
                                        profileForm.errors
                                            .foreign_student_country
                                    }
                                >
                                    <TextInput
                                        value={
                                            profileForm.data
                                                .foreign_student_country
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "foreign_student_country",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Кандас (указать страну)"
                                    error={profileForm.errors.kandas_country}
                                >
                                    <TextInput
                                        value={profileForm.data.kandas_country}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "kandas_country",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Проживает в общежитии"
                                    error={profileForm.errors.dormitory_details}
                                >
                                    <TextInput
                                        value={
                                            profileForm.data.dormitory_details
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "dormitory_details",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Проживает у родственников"
                                    error={
                                        profileForm.errors
                                            .relatives_living_details
                                    }
                                >
                                    <TextInput
                                        value={
                                            profileForm.data
                                                .relatives_living_details
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "relatives_living_details",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>

                                <Field
                                    label="Арендует жилье"
                                    error={
                                        profileForm.errors
                                            .rental_housing_details
                                    }
                                >
                                    <TextInput
                                        value={
                                            profileForm.data
                                                .rental_housing_details
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "rental_housing_details",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>
                            </div>
                        </Section>

                        <Section
                            title="Академический профиль"
                            actions={renderSectionSave()}
                        >
                            {renderBlockReview({
                                block: "academic",
                                statusLabel:
                                    academicProfile.academic_review_status_label,
                                reviewedAtDisplay:
                                    academicProfile.academic_reviewed_at_display,
                                comment:
                                    academicProfile.academic_review_comment,
                            })}
                            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                <Field
                                    label="Язык обучения"
                                    error={
                                        profileForm.errors.education_language
                                    }
                                >
                                    <SelectInput
                                        value={
                                            profileForm.data.education_language
                                        }
                                        options={options.educationLanguages}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "education_language",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Средний балл (GPA)"
                                    error={profileForm.errors.gpa}
                                >
                                    <TextInput
                                        type="number"
                                        min="0"
                                        max="4"
                                        step="0.01"
                                        value={profileForm.data.gpa}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "gpa",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
                                    />
                                </Field>
                            </div>

                            <div className="mt-5 grid gap-5 md:grid-cols-2">
                                <Field
                                    label="Итоговые оценки"
                                    error={profileForm.errors.final_grades}
                                >
                                    <TextAreaInput
                                        value={profileForm.data.final_grades}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "final_grades",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Текущая успеваемость"
                                    error={
                                        profileForm.errors.current_performance
                                    }
                                >
                                    <TextAreaInput
                                        value={
                                            profileForm.data.current_performance
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "current_performance",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Академическая задолженность"
                                    error={profileForm.errors.academic_debt}
                                >
                                    <TextAreaInput
                                        value={profileForm.data.academic_debt}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "academic_debt",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </Section>

                        <Section
                            title="Учебный статус"
                            actions={renderSectionSave()}
                            hidden={!isManagedProfile}
                            tone="danger"
                        >
                            <p className="mb-5 rounded-md bg-red-100 px-4 py-3 text-sm font-medium text-red-800 ring-1 ring-red-200">
                                Служебный блок. Изменение статуса на
                                &quot;Выбыл&quot; перенесет студента в список
                                выбывших в социальном паспорте группы.
                            </p>
                            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                <Field
                                    label="Статус студента"
                                    error={profileForm.errors.student_status}
                                >
                                    <SelectInput
                                        value={profileForm.data.student_status}
                                        options={options.studentStatuses}
                                        placeholder="Обучается"
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "student_status",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Причина выбытия"
                                    error={profileForm.errors.departure_reason}
                                >
                                    <SelectInput
                                        value={
                                            profileForm.data.departure_reason
                                        }
                                        options={options.departureReasons}
                                        disabled={
                                            profileForm.data.student_status !==
                                            "departed"
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "departure_reason",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Дата выбытия"
                                    error={profileForm.errors.departed_at}
                                >
                                    <TextInput
                                        type="date"
                                        value={profileForm.data.departed_at}
                                        disabled={
                                            profileForm.data.student_status !==
                                            "departed"
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "departed_at",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full disabled:cursor-not-allowed disabled:bg-gray-50"
                                    />
                                </Field>

                                <Field
                                    label="Другое"
                                    error={
                                        profileForm.errors
                                            .departure_reason_other
                                    }
                                    className="md:col-span-2 xl:col-span-3"
                                >
                                    <TextAreaInput
                                        value={
                                            profileForm.data
                                                .departure_reason_other
                                        }
                                        disabled={
                                            profileForm.data.student_status !==
                                                "departed" ||
                                            profileForm.data
                                                .departure_reason !== "other"
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "departure_reason_other",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </Section>
                    </form>
                    )}

                    {canEditHealthPassport && (
                        <section className="mt-8 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                                <h3 className="text-base font-semibold text-[#274f93]">
                                    Паспорт здоровья обучающегося
                                </h3>
                            </div>

                            <form onSubmit={submitHealthPassport}>
                                <div className="grid gap-5 p-6 md:grid-cols-2">
                                    <Field
                                        label="Флюорография: дата прохождения"
                                        error={
                                            healthPassportForm.errors
                                                .fluorography_date
                                        }
                                    >
                                        <TextInput
                                            type="date"
                                            value={
                                                healthPassportForm.data
                                                    .fluorography_date
                                            }
                                            onChange={(event) =>
                                                healthPassportForm.setData(
                                                    "fluorography_date",
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full"
                                        />
                                    </Field>

                                    <Field
                                        label="Флюорография: фото снимка"
                                        error={
                                            healthPassportForm.errors
                                                .fluorography_image
                                        }
                                    >
                                        <input
                                            key={healthPassportFileKey}
                                            type="file"
                                            accept="image/jpeg,image/png"
                                            onChange={(event) =>
                                                healthPassportForm.setData(
                                                    "fluorography_image",
                                                    event.target.files[0],
                                                )
                                            }
                                            className="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200"
                                        />
                                        <FileLink
                                            href={
                                                healthPassport.fluorography_image_url
                                            }
                                            label="Открыть текущий снимок"
                                        />
                                    </Field>

                                    <Field
                                        label="Диспансерный учет"
                                        error={
                                            healthPassportForm.errors
                                                .dispensary_accounting
                                        }
                                    >
                                        <SelectInput
                                            value={
                                                healthPassportForm.data
                                                    .dispensary_accounting
                                            }
                                            options={
                                                dispensaryAccountingOptions
                                            }
                                            placeholder="Выберите значение"
                                            onChange={(event) =>
                                                healthPassportForm.setData(
                                                    "dispensary_accounting",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Группа инвалидности"
                                        error={
                                            healthPassportForm.errors
                                                .disability_group
                                        }
                                    >
                                        <SelectInput
                                            value={
                                                healthPassportForm.data
                                                    .disability_group
                                            }
                                            options={options.disabilityGroups}
                                            placeholder="Не указано"
                                            onChange={(event) =>
                                                healthPassportForm.setData(
                                                    "disability_group",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Диагноз"
                                        error={healthPassportForm.errors.diagnosis}
                                        className="md:col-span-2"
                                    >
                                        <TextAreaInput
                                            value={
                                                healthPassportForm.data.diagnosis
                                            }
                                            onChange={(event) =>
                                                healthPassportForm.setData(
                                                    "diagnosis",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Психологический диагноз"
                                        error={
                                            healthPassportForm.errors
                                                .psychological_diagnosis
                                        }
                                        className="md:col-span-2"
                                    >
                                        <TextAreaInput
                                            value={
                                                healthPassportForm.data
                                                    .psychological_diagnosis
                                            }
                                            onChange={(event) =>
                                                healthPassportForm.setData(
                                                    "psychological_diagnosis",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Беременность"
                                        error={healthPassportForm.errors.pregnancy}
                                        className="md:col-span-2"
                                    >
                                        <TextAreaInput
                                            value={
                                                healthPassportForm.data.pregnancy
                                            }
                                            rows={3}
                                            onChange={(event) =>
                                                healthPassportForm.setData(
                                                    "pregnancy",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>

                                <div className="flex items-center justify-end gap-4 border-t border-gray-200 bg-gray-50 px-6 py-4">
                                    {healthPassportForm.recentlySuccessful && (
                                        <p className="text-sm text-gray-600">
                                            Сохранено
                                        </p>
                                    )}
                                    <PrimaryButton
                                        disabled={healthPassportForm.processing}
                                    >
                                        Сохранить
                                    </PrimaryButton>
                                </div>
                            </form>
                        </section>
                    )}

                    {canEditProfile && (
                    <div className="mt-8 grid gap-8 xl:grid-cols-2">
                        <section className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                                <h3 className="text-base font-semibold text-[#274f93]">
                                    Внеучебная деятельность
                                </h3>
                            </div>

                            <form
                                onSubmit={submitAchievement}
                                className="border-b border-gray-200 p-6"
                            >
                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        label="Тип"
                                        error={
                                            achievementForm.errors.activity_type
                                        }
                                    >
                                        <SelectInput
                                            value={
                                                achievementForm.data
                                                    .activity_type
                                            }
                                            options={options.activityTypes}
                                            placeholder="Выберите тип"
                                            onChange={(event) =>
                                                achievementForm.setData(
                                                    "activity_type",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Название"
                                        error={achievementForm.errors.title}
                                    >
                                        <TextInput
                                            value={achievementForm.data.title}
                                            onChange={(event) =>
                                                achievementForm.setData(
                                                    "title",
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full"
                                        />
                                    </Field>

                                    <Field
                                        label="Уровень участия"
                                        error={achievementForm.errors.level}
                                    >
                                        <SelectInput
                                            value={achievementForm.data.level}
                                            options={options.achievementLevels}
                                            onChange={(event) =>
                                                achievementForm.setData(
                                                    "level",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Результат"
                                        error={achievementForm.errors.result}
                                    >
                                        <SelectInput
                                            value={achievementForm.data.result}
                                            options={options.achievementResults}
                                            onChange={(event) =>
                                                achievementForm.setData(
                                                    "result",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Описание"
                                        error={
                                            achievementForm.errors.description
                                        }
                                        className="md:col-span-2"
                                    >
                                        <TextAreaInput
                                            value={
                                                achievementForm.data.description
                                            }
                                            onChange={(event) =>
                                                achievementForm.setData(
                                                    "description",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Подтверждающий документ"
                                        error={achievementForm.errors.document}
                                        className="md:col-span-2"
                                    >
                                        <input
                                            key={achievementFileKey}
                                            type="file"
                                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.mp4"
                                            onChange={(event) =>
                                                achievementForm.setData(
                                                    "document",
                                                    event.target.files[0],
                                                )
                                            }
                                            className="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200"
                                        />
                                        <p className="mt-2 text-xs text-gray-500">
                                            PDF, DOCX, JPG, PNG, MP4
                                        </p>
                                    </Field>
                                </div>

                                <div className="mt-5 flex justify-end">
                                    <SecondaryButton
                                        type="submit"
                                        disabled={achievementForm.processing}
                                    >
                                        Добавить
                                    </SecondaryButton>
                                </div>
                            </form>

                            <div className="divide-y divide-gray-200">
                                {achievements.length === 0 && (
                                    <p className="p-6 text-sm text-gray-500">
                                        Записей нет
                                    </p>
                                )}
                                {achievements.map((achievement) => (
                                    <div
                                        key={achievement.id}
                                        className="flex flex-col gap-4 p-6 md:flex-row md:items-start md:justify-between"
                                    >
                                        <div>
                                            <p className="font-medium text-gray-900">
                                                {achievement.title}
                                            </p>
                                            <p className="mt-1 text-sm text-gray-600">
                                                {optionLabel(
                                                    options.activityTypes,
                                                    achievement.activity_type,
                                                )}{" "}
                                                ·{" "}
                                                {optionLabel(
                                                    options.achievementLevels,
                                                    achievement.level,
                                                )}{" "}
                                                ·{" "}
                                                {optionLabel(
                                                    options.achievementResults,
                                                    achievement.result,
                                                )}
                                            </p>
                                            {achievement.description && (
                                                <p className="mt-2 text-sm text-gray-600">
                                                    {achievement.description}
                                                </p>
                                            )}
                                            <FileLink
                                                href={achievement.document_url}
                                                label={
                                                    achievement.document_original_name ??
                                                    "Открыть документ"
                                                }
                                            />
                                        </div>

                                        <DangerButton
                                            type="button"
                                            onClick={() =>
                                                router.delete(
                                                    achievementDestroyUrl(
                                                        achievement.id,
                                                    ),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Удалить
                                        </DangerButton>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                                <h3 className="text-base font-semibold text-[#274f93]">
                                    Цифровое портфолио
                                </h3>
                            </div>

                            <form
                                onSubmit={submitPortfolio}
                                className="border-b border-gray-200 p-6"
                            >
                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        label="Тип"
                                        error={portfolioForm.errors.item_type}
                                    >
                                        <SelectInput
                                            value={portfolioForm.data.item_type}
                                            options={options.portfolioTypes}
                                            placeholder="Выберите тип"
                                            onChange={(event) =>
                                                portfolioForm.setData(
                                                    "item_type",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Название"
                                        error={portfolioForm.errors.title}
                                    >
                                        <TextInput
                                            value={portfolioForm.data.title}
                                            onChange={(event) =>
                                                portfolioForm.setData(
                                                    "title",
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full"
                                        />
                                    </Field>

                                    <Field
                                        label="Файл"
                                        error={portfolioForm.errors.file}
                                        className="md:col-span-2"
                                    >
                                        <input
                                            key={portfolioFileKey}
                                            type="file"
                                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.mp4"
                                            onChange={(event) =>
                                                portfolioForm.setData(
                                                    "file",
                                                    event.target.files[0],
                                                )
                                            }
                                            className="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200"
                                        />
                                        <p className="mt-2 text-xs text-gray-500">
                                            PDF, DOCX, JPG, PNG, MP4
                                        </p>
                                    </Field>
                                </div>

                                <div className="mt-5 flex justify-end">
                                    <SecondaryButton
                                        type="submit"
                                        disabled={portfolioForm.processing}
                                    >
                                        Загрузить
                                    </SecondaryButton>
                                </div>
                            </form>

                            <div className="divide-y divide-gray-200">
                                {portfolioItems.length === 0 && (
                                    <p className="p-6 text-sm text-gray-500">
                                        Файлов нет
                                    </p>
                                )}
                                {portfolioItems.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex flex-col gap-4 p-6 md:flex-row md:items-start md:justify-between"
                                    >
                                        <div>
                                            <p className="font-medium text-gray-900">
                                                {item.title}
                                            </p>
                                            <p className="mt-1 text-sm text-gray-600">
                                                {optionLabel(
                                                    options.portfolioTypes,
                                                    item.item_type,
                                                )}{" "}
                                                · {item.original_name}{" "}
                                                <FileSize size={item.size} />
                                            </p>
                                            <FileLink
                                                href={item.file_url}
                                                label="Открыть файл"
                                            />
                                        </div>

                                        <DangerButton
                                            type="button"
                                            onClick={() =>
                                                router.delete(
                                                    portfolioDestroyUrl(
                                                        item.id,
                                                    ),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Удалить
                                        </DangerButton>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
