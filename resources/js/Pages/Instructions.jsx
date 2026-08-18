import ApplicationLogo from "@/Components/ApplicationLogo";
import LanguageSwitcher from "@/Components/LanguageSwitcher";
import LanguageDomTranslator from "@/i18n/LanguageDomTranslator";
import { Head, Link, usePage } from "@inertiajs/react";
import { useState } from "react";

const primaryButton =
    "inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2";

const psychotestLinks = [
    {
        id: 1,
        title: "Тест «Шкала адаптации»",
    },
    {
        id: 2,
        title: "Шкала тревоги и депрессии (HADS)",
    },
    {
        id: 3,
        title: "Опросник С.Г. Корчагиной «Одиночество»",
    },
    {
        id: 4,
        title: "Шкала психологического стресса",
    },
    {
        id: 5,
        title: "Опросник для определения типа темперамента",
    },
    {
        id: 6,
        title: "Тест для определения типа характера",
    },
    {
        id: 7,
        title: "Шкала самооценки Розенберга",
    },
    {
        id: 8,
        title: "Изучение социального самочувствия иностранных обучающихся",
    },
    {
        id: 9,
        title: "Качество образования глазами выпускника",
    },
    {
        id: 10,
        title: "Удовлетворенность студентов обучением в АО «АТУ»",
    },
].map((test) => ({
    label: `${test.id}. ${test.title}`,
    href: `https://psychologist.atu.kz/tests/${test.id}`,
}));

const guestSteps = [
    {
        title: "Вход в систему",
        route: "/login",
        text: "Откройте страницу входа и введите email или номер телефона, затем пароль. Если вы входите через Платонус, используйте отдельную страницу входа через Платонус.",
        screenshot:
            "страница /login с формой Email / телефон и страница /login/platonus.",
        // image: "login.png",
        images: ["login.png", "login2.png"],
    },
];

const studentSteps = [
    {
        title: "Вход в систему",
        route: "/login",
        text: "Откройте страницу входа и введите email или номер телефона, затем пароль. Если студент входит через Платонус, используйте отдельную страницу входа через Платонус.",
        screenshot:
            "страница /login с активной формой Email / телефон и отдельная страница /login/platonus.",
        images: ["login.png", "login2.png"],
    },
    {
        title: "Главная страница студента",
        route: "/dashboard",
        text: "После авторизации студент видит свои основные блоки: личную информацию, успеваемость, достижения, портфолио и рекомендации.",
        screenshot: "главная страница студента после входа.",
        image: "student-dashboard.png",
    },
    {
        title: "Психологические тесты",
        route: "https://psychologist.atu.kz/tests/1-10",
        href: "https://psychologist.atu.kz/tests/1",
        text: "Студент должен пройти психологические тесты на отдельной платформе психологического сопровождения. Откройте каждый тест по очереди, заполните ответы и завершите прохождение.",
        screenshot:
            "страница теста на psychologist.atu.kz с вопросами и кнопкой завершения.",
        links: psychotestLinks,
        image: "psychotests.png",
    },
    {
        title: "Карточка студента",
        route: "/student-profile",
        text: "В этом разделе студент заполняет основные данные: ФИО, дату рождения, гражданство, факультет, группу, специальность, курс, семейное положение и другие сведения.",
        screenshot: "страница /student-profile, блок “Карточка студента”.",
        image: "student-card.png",
    },
    {
        title: "Социальный статус",
        route: "/student-profile",
        text: "Студент указывает социальный статус, инвалидность, сведения о родителях, сиротство, полусиротство, льготы и необходимость социальной поддержки.",
        screenshot:
            "блок “Социальный статус” с выбранными селектами и чекбоксами.",
        images: ["social-status.png", "social-status2.png"],
    },
    {
        title: "Контакты и проживание",
        route: "/student-profile",
        text: "Здесь заполняются адрес пребывания, адрес проживания, контакты, email, данные родителей или опекунов, общежитие, родственники, аренда жилья, иностранный студент и кандас.",
        screenshot: "блок “Контакты и проживание” на странице анкеты студента.",
        image: "contacts-residence.png",
    },
    {
        title: "Академический профиль",
        route: "/student-profile",
        text: "Студент или ответственный сотрудник заполняет язык обучения, GPA, итоговые оценки, текущую успеваемость и академическую задолженность.",
        screenshot: "блок “Академический профиль” с заполненными полями.",
        image: "academic-profile.png",
    },
    {
        title: "Достижения",
        route: "/student-profile",
        text: "В разделе внеучебной деятельности добавляются олимпиады, конкурсы, спорт, волонтерство, клубы, проекты, публикации и другие достижения.",
        screenshot:
            "форма добавления достижения и список уже добавленных достижений.",
        image: "achievements.png",
    },
    {
        title: "Цифровое портфолио",
        route: "/student-profile",
        text: "Студент загружает сертификаты, дипломы, грамоты, проекты, научные работы, видеоматериалы, благодарственные письма и другие документы.",
        screenshot:
            "блок “Цифровое портфолио студента” с кнопкой загрузки файла.",
        image: "achievements.png",
    },
];

const groupLeaderSteps = [
    {
        title: "Социальный паспорт группы",
        route: "/groups/{id}/social-passport",
        text: "Староста открывает социальный паспорт своей группы и проверяет сведения по группе и студентам.",
        screenshot:
            "верхняя часть /groups/{id}/social-passport с факультетом, группой и данными ответственных.",
        image: "group-social-passport.png",
    },
    {
        title: "Студенты группы",
        route: "/groups/{id}/social-passport",
        text: "Список студентов выводится строками. Для просмотра полной карточки нужно нажать “Подробнее” рядом с нужным студентом.",
        screenshot:
            "таблица или список “Студенты группы” с кнопкой “Подробнее”.",
        image: "group-students.png",
    },
    {
        title: "Автоматический подсчет социального статуса",
        route: "/groups/{id}/social-passport",
        text: "Количество студентов по социальным категориям считается из заполненных анкет студентов выбранной группы.",
        screenshot: "блок “Социальный статус” в социальном паспорте группы.",
        image: "group-social-status-summary.png",
    },
    {
        title: "Выбывшие студенты",
        route: "/groups/{id}/social-passport",
        text: "Если студент выбыл, в его карточке меняется учебный статус. После этого он отображается в блоке выбывших студентов с причиной.",
        screenshot:
            "блок “Выбывшие студенты” и учебный статус в карточке студента.",
        image: "expelled-students.png",
    },
];

const curatorSteps = [
    {
        title: "Создание группы",
        route: "/groups",
        text: "Куратор или эдвайзер открывает список групп, нажимает “Создать группу”, выбирает факультет, курс и вводит название группы.",
        screenshot: "страница /groups и модальное окно “Создать группу”.",
        images: ["groups-create.png", "groups-create2.png"],
    },
    {
        title: "Социальный паспорт группы",
        route: "/groups/{id}/social-passport",
        text: "После создания группы открывается социальный паспорт группы. Данные куратора или эдвайзера подставляются автоматически из пользователя, который создал группу.",
        screenshot:
            "верхняя часть /groups/{id}/social-passport с факультетом, группой и данными куратора / эдвайзера.",
        images: [
            "group-social-passport-header.png",
            "group-social-passport-header2.png",
        ],
    },
    {
        title: "Назначение старосты",
        route: "/groups/{id}/social-passport",
        text: "В поле “Староста” выберите студента из этой группы. После сохранения система назначает ему роль старосты, а у предыдущего старосты роль убирается.",
        screenshot: "select “Староста” со списком студентов группы.",
        image: "group-leader-select.png",
    },
    {
        title: "Студенты группы",
        route: "/groups/{id}/social-passport",
        text: "Список студентов выводится строками. Для просмотра полной карточки нужно нажать “Подробнее” рядом с нужным студентом.",
        screenshot:
            "таблица или список “Студенты группы” с кнопкой “Подробнее”.",
        image: "group-students.png",
    },
    {
        title: "Автоматический подсчет социального статуса",
        route: "/groups/{id}/social-passport",
        text: "Количество студентов по социальным категориям считается из заполненных анкет студентов выбранной группы.",
        screenshot: "блок “Социальный статус” в социальном паспорте группы.",
        image: "group-social-status-summary.png",
    },
    {
        title: "Выбывшие студенты",
        route: "/groups/{id}/social-passport",
        text: "Если студент выбыл, в его карточке меняется учебный статус. После этого он отображается в блоке выбывших студентов с причиной.",
        screenshot:
            "блок “Выбывшие студенты” и учебный статус в карточке студента.",
        image: "group-social-status-summary.png",
    },
];

const administrationSteps = [
    {
        title: "Портреты студентов",
        route: "/student-profiles",
        text: "Уполномоченные роли видят список портретов студентов, могут фильтровать записи и открывать подробную карточку конкретного студента.",
        screenshot: "страница /student-profiles со списком и фильтрами.",
        image: "student-profiles-list.png",
    },
    {
        title: "Психологические данные",
        route: "/student-profiles/{id}",
        text: "Результаты психотестов показываются внутри карточки студента только тем ролям, которым разрешен доступ к психологическим данным.",
        screenshot: "блок “Результаты психотестов” в карточке студента.",
        image: "psychotest-results.png",
    },
    {
        title: "Паспорт здоровья",
        route: "/student-profiles/{id}",
        text: "Медицинские сведения привязываются к конкретному студенту. Здравпункт видит нужные медицинские поля и карточку студента без редактирования лишних данных.",
        screenshot:
            "блок “Паспорт здоровья обучающегося” внутри карточки студента.",
        image: "health-passport.png",
    },
    {
        title: "Аналитическая панель",
        route: "/analytics-dashboard",
        text: "Администрация видит общую статистику, риски, рейтинги, мониторинг показателей, рекомендации, уведомления и отчеты.",
        screenshot:
            "страница /analytics-dashboard с графиками, рейтингами и блоками рисков.",
        images: ["analytics-dashboard.png", "analytics-dashboard2.png"],
    },
    {
        title: "Отчетность",
        route: "/analytics-dashboard",
        text: "Отчеты формируются по студенту, группе, курсу, факультету и рискам. Экспорт доступен в Excel.",
        screenshot: "блок отчетов и кнопка экспорта Excel.",
        image: "reports-export.png",
    },
];

const ditSteps = [
    {
        title: "Пользователи",
        route: "/users",
        text: "Администратор ДИТ открывает список всех пользователей, ищет аккаунты по ФИО, email, телефону, логину Платонуса и фильтрует записи по роли.",
        screenshot:
            "страница /users со списком пользователей, поиском и фильтром по роли.",
        image: "users-list.png",
    },
    {
        title: "Войти как пользователь",
        route: "/users",
        text: "Для проверки доступа нажмите “Войти как” рядом с нужным пользователем. Система откроет сайт от имени выбранного аккаунта.",
        screenshot: "кнопка “Войти как” в строке пользователя.",
        image: "impersonate-button.png",
    },
    {
        title: "Вернуться в ДИТ",
        route: "/impersonation/stop",
        text: "Во время входа как другой пользователь сверху отображается баннер. Нажмите “Вернуться в ДИТ”, чтобы вернуться в аккаунт администратора.",
        screenshot:
            "желтый баннер режима входа как пользователь с кнопкой “Вернуться в ДИТ”.",
        image: "impersonation-banner.png",
    },
];

const routeFallbacks = {
    "/groups/{id}/social-passport": "/groups",
    "/student-profiles/{id}": "/student-profiles",
    "/impersonation/stop": "/users",
};

function isExternalUrl(href) {
    return href.startsWith("http://") || href.startsWith("https://");
}

function stepHref(step) {
    if (step.href) {
        return step.href;
    }

    if (routeFallbacks[step.route]) {
        return routeFallbacks[step.route];
    }

    if (!step.route || step.route.includes("{")) {
        return null;
    }

    return step.route;
}

function buildInstructionSections(auth) {
    const user = auth?.user;
    const roleSlug = user?.role?.slug;

    if (!user) {
        return {
            intro: "Для гостей показана только инструкция по входу. После авторизации система откроет инструкцию именно для вашей роли.",
            sections: [
                {
                    title: "Вход в систему",
                    description:
                        "Эти шаги нужны, чтобы открыть личный кабинет и перейти к инструкции своей роли.",
                    steps: guestSteps,
                },
            ],
        };
    }

    if (roleSlug === "student") {
        return {
            intro: "Показана только инструкция студента: вход, анкета, достижения и портфолио.",
            sections: [
                {
                    title: "Студент",
                    description:
                        "Эти шаги показывают, как студент входит в систему, заполняет анкету, добавляет достижения и загружает портфолио.",
                    steps: studentSteps,
                },
            ],
        };
    }

    if (roleSlug === "group_leader") {
        return {
            intro: "Показаны инструкции старосты: личная анкета и работа с данными своей группы.",
            sections: [
                {
                    title: "Студент",
                    description:
                        "Староста также заполняет собственную анкету студента.",
                    steps: studentSteps,
                },
                {
                    title: "Староста",
                    description:
                        "Эти шаги нужны для просмотра и ведения данных своей группы.",
                    steps: groupLeaderSteps,
                },
            ],
        };
    }

    if (roleSlug === "curator" || roleSlug === "advisor") {
        return {
            intro: "Показана инструкция куратора / эдвайзера: группы, социальный паспорт и работа со студентами.",
            sections: [
                {
                    title: "Куратор / эдвайзер",
                    description:
                        "Эти шаги нужны для работы с группами, социальным паспортом группы, назначением старосты и просмотром студентов.",
                    steps: curatorSteps,
                },
            ],
        };
    }

    if (roleSlug === "administrator_dit") {
        return {
            intro: "Администратор ДИТ видит полную инструкцию по системе, включая управление пользователями и вход от имени пользователя.",
            sections: [
                {
                    title: "Администратор ДИТ",
                    description:
                        "Эти шаги нужны для управления пользователями и проверки доступа разных ролей.",
                    steps: ditSteps,
                },
                {
                    title: "Студент",
                    description:
                        "Инструкция по заполнению анкеты, достижениям и портфолио студента.",
                    steps: studentSteps,
                },
                {
                    title: "Куратор / эдвайзер / староста",
                    description:
                        "Инструкция по группам, социальному паспорту и работе со студентами.",
                    steps: curatorSteps,
                },
                {
                    title: "Администрация и ответственные службы",
                    description:
                        "Инструкция по просмотру портретов студентов, психологических и медицинских данных, аналитики и отчетов.",
                    steps: administrationSteps,
                },
            ],
        };
    }

    return {
        intro: "Показана инструкция для администрации и ответственных служб согласно доступам роли.",
        sections: [
            {
                title: "Администрация и ответственные службы",
                description:
                    "Эти шаги описывают просмотр портретов студентов, психологических и медицинских данных, аналитики и отчетов.",
                steps: administrationSteps,
            },
        ],
    };
}

function ScreenshotImage({ image, text }) {
    const [imageFailed, setImageFailed] = useState(false);
    const imagePath = `/images/instructions/${image}`;

    if (imageFailed) {
        return (
            <div className="flex min-h-[220px] items-center justify-center rounded-md border-2 border-dashed border-[#9fb8e2] bg-[#f6f9fe] px-5 py-8 text-center">
                <div>
                    <p className="text-sm font-semibold text-[#274f93]">
                        Какой скриншот нужен
                    </p>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-700">
                        {text}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <img
            src={imagePath}
            alt={text}
            onError={() => setImageFailed(true)}
            className="w-full rounded-md border border-[#dbe5f6] bg-white object-contain"
        />
    );
}

function ScreenshotSlot({ image, images, text }) {
    const imageList = images || (image ? [image] : []);

    return (
        <div className="mt-5 rounded-md border border-[#dbe5f6] bg-white p-4">
            {imageList.length > 0 ? (
                <div className="mt-3 grid gap-3">
                    {imageList.map((item) => (
                        <ScreenshotImage key={item} image={item} text={text} />
                    ))}
                </div>
            ) : (
                <div className="mt-3 flex min-h-[260px] items-center justify-center rounded-md border-2 border-dashed border-[#9fb8e2] bg-[#f6f9fe] px-5 py-8 text-center">
                    <div>
                        <p className="text-sm font-semibold text-[#274f93]">
                            Какой скриншот нужен
                        </p>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-700">
                            {text}
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}

function RouteBadge({ step }) {
    const href = stepHref(step);
    const className =
        "w-fit rounded-md border border-[#c9d8f0] bg-white px-2.5 py-1 text-xs font-medium text-[#355da8] transition hover:bg-[#f5f8fd] hover:text-[#274f93]";

    if (!href) {
        return <span className={className}>{step.route}</span>;
    }

    if (isExternalUrl(href)) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noreferrer"
                className={className}
            >
                {step.route}
            </a>
        );
    }

    return (
        <Link href={href} className={className}>
            {step.route}
        </Link>
    );
}

function StepLinks({ links = [] }) {
    if (links.length === 0) {
        return null;
    }

    return (
        <div className="mt-4 rounded-md border border-[#dbe5f6] bg-white p-4">
            <p className="text-sm font-semibold text-gray-900">
                Ссылки для прохождения
            </p>
            <div className="mt-3 space-y-2">
                {links.map((link) => (
                    <a
                        key={link.href}
                        href={link.href}
                        target="_blank"
                        rel="noreferrer"
                        className="flex items-center justify-between gap-3 rounded-md border border-[#dbe5f6] bg-[#f6f9fe] px-3 py-2 text-sm font-medium text-[#355da8] transition hover:border-[#355da8] hover:bg-[#edf3ff]"
                    >
                        <span>{link.label}</span>
                        <span className="truncate text-xs text-[#426aa8]">
                            {link.href}
                        </span>
                    </a>
                ))}
            </div>
        </div>
    );
}

function StepCard({ index, step }) {
    return (
        <article className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-[#dfe7f5]">
            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 className="text-base font-semibold text-[#274f93]">
                        {index}. {step.title}
                    </h3>
                    <RouteBadge step={step} />
                </div>
            </div>
            <div className="p-5">
                <p className="text-sm leading-6 text-gray-700">{step.text}</p>
                <StepLinks links={step.links} />
                <ScreenshotSlot
                    image={step.image}
                    images={step.images}
                    text={step.screenshot}
                />
            </div>
        </article>
    );
}

function Section({ title, description, steps, startIndex }) {
    return (
        <section className="mt-10">
            <div className="mb-5">
                <h2 className="text-2xl font-semibold text-gray-950">
                    {title}
                </h2>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-gray-600">
                    {description}
                </p>
            </div>

            <div className="grid gap-4">
                {steps.map((step, stepIndex) => (
                    <StepCard
                        key={`${title}-${step.title}`}
                        index={startIndex + stepIndex}
                        step={step}
                    />
                ))}
            </div>
        </section>
    );
}

export default function Instructions() {
    const { auth = {} } = usePage().props;
    const isAuthenticated = Boolean(auth?.user);
    const instruction = buildInstructionSections(auth);
    let stepStart = 1;

    return (
        <>
            <LanguageDomTranslator />
            <Head title="Инструкция" />

            <main className="min-h-screen bg-white text-gray-900">
                <header className="border-b border-[#e7eef8] bg-white">
                    <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                        <Link
                            href="/"
                            className="flex min-w-0 items-center gap-3"
                        >
                            <ApplicationLogo
                                variant="wordmark"
                                className="h-10 w-auto max-w-[170px] shrink-0 sm:h-11 sm:max-w-[220px]"
                                alt="Almaty Technological University"
                            />
                            <span className="hidden text-sm font-medium text-gray-500 md:inline">
                                Инструкция пользователя
                            </span>
                        </Link>

                        <nav className="flex shrink-0 items-center gap-2">
                            <LanguageSwitcher compact />
                            <Link
                                href={
                                    isAuthenticated
                                        ? route("dashboard")
                                        : route("login")
                                }
                                className={primaryButton}
                            >
                                {isAuthenticated ? "Кабинет" : "Войти"}
                            </Link>
                        </nav>
                    </div>
                </header>

                <section className="border-b border-[#e7eef8] bg-[#f5f8fd]">
                    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                        <div className="max-w-4xl">
                            <p className="text-sm font-semibold uppercase tracking-wide text-[#355da8]">
                                Student Profile ATU
                            </p>
                            <h1 className="mt-3 text-3xl font-semibold tracking-normal text-gray-950 sm:text-4xl">
                                Инструкция по работе с системой
                            </h1>
                            <p className="mt-4 text-base leading-7 text-gray-700">
                                {instruction.intro}
                            </p>
                        </div>
                    </div>
                </section>

                <div className="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
                    {instruction.sections.map((section) => {
                        const startIndex = stepStart;
                        stepStart += section.steps.length;

                        return (
                            <Section
                                key={section.title}
                                title={section.title}
                                description={section.description}
                                steps={section.steps}
                                startIndex={startIndex}
                            />
                        );
                    })}
                </div>
            </main>
        </>
    );
}
