export function formatInventoryQuantity(value) {
    const text = String(value);
    if (!/^-?\d+(?:\.\d+)?$/.test(text)) return text;
    const trimmed = text.includes('.') ? text.replace(/0+$/, '').replace(/\.$/, '') : text;
    return /^-?0$/.test(trimmed) ? '0' : trimmed;
}
