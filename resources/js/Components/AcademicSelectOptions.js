export function withCurrentOption(options = [], value = '') {
    if (!value || options.some((option) => option.value === value)) {
        return options;
    }

    return [{ value, label: value }, ...options];
}

export function specialtiesForFaculty(options = [], faculty = '', currentValue = '') {
    const visible = options.filter((option) => !faculty || !option.faculty || option.faculty === faculty);

    return withCurrentOption(visible, currentValue);
}
