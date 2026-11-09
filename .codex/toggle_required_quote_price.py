from pathlib import Path

path = Path('resources/js/Pages/Dashboard/CRM/InquiryShow.vue')
text = path.read_text(encoding='utf-8')
needle = 'inputmode="decimal" required class="field-input !pl-8"'
replacement = 'inputmode="decimal" :required="item.isIncluded !== false" class="field-input !pl-8"'
if text.count(needle) != 1:
    raise SystemExit(f'Expected one matching quotation price input, found {text.count(needle)}')
path.write_text(text.replace(needle, replacement), encoding='utf-8', newline='')
