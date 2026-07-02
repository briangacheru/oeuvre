function wrapSelection(prefix, suffix = '') {
    const textarea = document.getElementById('description');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);

    if (selectedText.length === 0) {
        // If no text is selected, insert placeholder
        textarea.setRangeText(`${prefix}text here${suffix}`);
        // Position cursor between the tags
        const newCursorPos = start + prefix.length;
        textarea.setSelectionRange(newCursorPos, newCursorPos + 9); // 9 is length of "text here"
    } else {
        textarea.setRangeText(`${prefix}${selectedText}${suffix}`);
    }
    textarea.focus();
}

function addLineBreak() {
    const textarea = document.getElementById('description');
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.substring(0, cursorPos);
    const textAfter = textarea.value.substring(cursorPos, textarea.value.length);
    textarea.value = textBefore + '\n' + textAfter;
    textarea.selectionStart = cursorPos + 1;
    textarea.selectionEnd = cursorPos + 1;
    textarea.focus();
}

function insertBullet() {
    const textarea = document.getElementById('description');
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.substring(0, cursorPos);
    const textAfter = textarea.value.substring(cursorPos, textarea.value.length);

    // Check if we need to add a newline first
    const needsNewline = textBefore.length > 0 && !textBefore.endsWith('\n');
    const bulletPrefix = needsNewline ? '\n- ' : '- ';

    textarea.value = textBefore + bulletPrefix + textAfter;
    textarea.selectionStart = cursorPos + bulletPrefix.length;
    textarea.selectionEnd = cursorPos + bulletPrefix.length;
    textarea.focus();
}

function insertNumberedList() {
    const textarea = document.getElementById('description');
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.substring(0, cursorPos);
    const textAfter = textarea.value.substring(cursorPos, textarea.value.length);

    // Check if we need to add a newline first
    const needsNewline = textBefore.length > 0 && !textBefore.endsWith('\n');
    const numberPrefix = needsNewline ? '\n1. ' : '1. ';

    textarea.value = textBefore + numberPrefix + textAfter;
    textarea.selectionStart = cursorPos + numberPrefix.length;
    textarea.selectionEnd = cursorPos + numberPrefix.length;
    textarea.focus();
}

function insertLink() {
    const textarea = document.getElementById('description');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);

    if (selectedText.length === 0) {
        textarea.setRangeText('[link text](https://example.com)');
        // Position cursor on the URL
        textarea.setSelectionRange(start + 12, start + 29); // Select "https://example.com"
    } else {
        textarea.setRangeText(`[${selectedText}](https://example.com)`);
        // Position cursor on the URL
        textarea.setSelectionRange(start + selectedText.length + 3, start + selectedText.length + 20);
    }
    textarea.focus();
}

function insertTable() {
    const textarea = document.getElementById('description');
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.substring(0, cursorPos);
    const textAfter = textarea.value.substring(cursorPos, textarea.value.length);

    // Check if we need to add a newline first
    const needsNewline = textBefore.length > 0 && !textBefore.endsWith('\n');
    const newline = needsNewline ? '\n' : '';

    const tableTemplate =
        `${newline}| Header 1 | Header 2 | Header 3 |
| -------- | -------- | -------- |
| Cell 1   | Cell 2   | Cell 3   |
| Cell 4   | Cell 5   | Cell 6   |`;

    textarea.value = textBefore + tableTemplate + textAfter;
    textarea.selectionStart = cursorPos + newline.length + 2;
    textarea.selectionEnd = cursorPos + newline.length + 10; // Select "Header 1"
    textarea.focus();
}

function insertCodeBlock() {
    const textarea = document.getElementById('description');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);

    if (selectedText.length === 0) {
        textarea.setRangeText("```\ncode here\n```");
        // Position cursor in the middle of the code block
        textarea.setSelectionRange(start + 4, start + 13); // Select "code here"
    } else {
        textarea.setRangeText("```\n" + selectedText + "\n```");
    }
    textarea.focus();
}