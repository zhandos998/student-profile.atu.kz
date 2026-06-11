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

function Section({ title, children, actions = null }) {
    return (
        <section className="border-b border-gray-200 bg-white p-6 last:border-b-0">
            <h3 className="mb-5 text-base font-semibold text-gray-900">
                {title}
            </h3>
            {children}
            {actions && (
                <div className="mt-6 flex items-center justify-end gap-4 border-t border-gray-100 pt-4">
                    {actions}
                </div>
            )}
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
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-50"
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
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-50"
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
            className="mt-2 inline-flex text-sm font-medium text-indigo-700 hover:text-indigo-900"
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
    achievements,
    portfolioItems,
    options,
    isManagedProfile = false,
    targetUser = null,
}) {
    const [achievementFileKey, setAchievementFileKey] = useState(0);
    const [portfolioFileKey, setPortfolioFileKey] = useState(0);
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
        grade_dynamics: valueOrEmpty(academicProfile.grade_dynamics),
        group_comparison: valueOrEmpty(academicProfile.group_comparison),
        success_forecast: valueOrEmpty(academicProfile.success_forecast),
    });
    const [profileErrors, setProfileErrors] = useState({});
    const [profileProcessing, setProfileProcessing] = useState(false);
    const [profileRecentlySuccessful, setProfileRecentlySuccessful] =
        useState(false);

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
    const targetUserId = targetUser?.id;
    const profileUpdateUrl = isManagedProfile
        ? route("student-profiles.update", targetUserId)
        : route("student-profile.update");
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
                    <form
                        onSubmit={submitProfile}
                        className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
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
                                            profileForm.setData(
                                                "faculty",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Группа"
                                    error={profileForm.errors.group_name}
                                >
                                    <TextInput
                                        value={profileForm.data.group_name}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "group_name",
                                                event.target.value,
                                            )
                                        }
                                        className="w-full"
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
                                        min="1900"
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
                                        value={
                                            profileForm.data.kandas_country
                                        }
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

                                <Field
                                    label="Динамика оценок"
                                    error={profileForm.errors.grade_dynamics}
                                >
                                    <TextAreaInput
                                        value={profileForm.data.grade_dynamics}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "grade_dynamics",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Сравнение с группой"
                                    error={profileForm.errors.group_comparison}
                                >
                                    <TextAreaInput
                                        value={
                                            profileForm.data.group_comparison
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "group_comparison",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Прогноз академической успешности"
                                    error={profileForm.errors.success_forecast}
                                >
                                    <TextAreaInput
                                        value={
                                            profileForm.data.success_forecast
                                        }
                                        onChange={(event) =>
                                            profileForm.setData(
                                                "success_forecast",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </Section>
                    </form>

                    <div className="mt-8 grid gap-8 xl:grid-cols-2">
                        <section className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-gray-200 p-6">
                                <h3 className="text-base font-semibold text-gray-900">
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
                            <div className="border-b border-gray-200 p-6">
                                <h3 className="text-base font-semibold text-gray-900">
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
