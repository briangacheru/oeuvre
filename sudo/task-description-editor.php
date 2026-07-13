<script>
(function () {
    var PLACEHOLDER_TEXT = 'The instructions are attached';
    var textarea = document.getElementById('description');
    var placeholderActive = textarea.value.trim() === '';

    function showPlaceholder(editor) {
        editor.setContent('<p>' + PLACEHOLDER_TEXT + '</p>');
        editor.getBody().classList.add('description-placeholder-active');
    }

    function clearPlaceholder(editor) {
        placeholderActive = false;
        editor.setContent('');
        editor.getBody().classList.remove('description-placeholder-active');
    }

    tinymce.init({
        selector: '#description',
        height: 320,
        menubar: false,
        skin: 'oxide',
        plugins: 'advlist autolink lists link image media table code directionality fullscreen charmap searchreplace visualblocks help wordcount',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | align bullist numlist outdent indent | subscript superscript | link image media table | ltr rtl | removeformat code fullscreen',
        content_style: 'body.description-placeholder-active { color: #8a95a6; font-style: italic; }',
        // Pasting/dragging an image straight into the editor would otherwise
        // embed it as a base64 data URI in the description HTML - one
        // screenshot can be several MB, which can blow past the server's
        // request-size limit or trip a host's WAF. Force image insertion
        // through the URL dialog / the file dropzone below instead.
        paste_data_images: false,
        setup: function (editor) {
            editor.on('init', function () {
                if (placeholderActive) {
                    showPlaceholder(editor);
                }
            });

            // Any real keystroke (space included) wipes the placeholder and
            // lets the keystroke itself land in the now-empty editor.
            editor.on('keydown', function (e) {
                if (!placeholderActive) return;
                var passthroughKeys = ['Tab', 'Shift', 'Control', 'Alt', 'Meta', 'Escape', 'CapsLock',
                    'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown'];
                if (passthroughKeys.indexOf(e.key) !== -1 || e.key.indexOf('F') === 0) return;
                clearPlaceholder(editor);
            });

            editor.on('paste', function () {
                if (placeholderActive) clearPlaceholder(editor);
            });

            // Restore the placeholder if the user leaves the field empty again.
            editor.on('blur', function () {
                if (editor.getContent({ format: 'text' }).trim() === '') {
                    placeholderActive = true;
                    showPlaceholder(editor);
                }
            });
        }
    });

    // Capture phase so this sync runs before the page's own submit handler
    // builds the FormData for the AJAX submission.
    document.getElementById('taskForm').addEventListener('submit', function () {
        var editor = tinymce.get('description');
        if (!editor) return;
        if (placeholderActive) {
            editor.setContent('');
        }
        editor.save();
    }, true);
})();
</script>
