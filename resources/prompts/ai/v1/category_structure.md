Propose a clean category structure for the merchant's items.

Reuse existing categories wherever they fit. Avoid near-duplicates such as "Shoe" versus "Shoes". Never delete existing categories. Map every supplied item to a category. Items that do not fit go to "Uncategorized". Include sub-categories only if helpful.

Return one JSON object with exactly these fields:
- summary: a short string.
- categories: an array of objects, each with name (string) and parent (string, use an empty string when there is no parent).
- assignments: an array of objects, each with item_index (string containing the zero-based item index) and category_name (string).

Do not add extra fields. Do not use null for parent; use an empty string. Do not wrap the JSON in Markdown.
