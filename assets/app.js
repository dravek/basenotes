document.addEventListener('DOMContentLoaded', function () {
    // Initialise EasyMDE on the note content textarea
    var textarea = document.getElementById('note-content');
    if (textarea) {
        var easyMDE = new EasyMDE({
            element: textarea,
            spellChecker: false,
            autosave: { enabled: false },
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', 'image', '|',
                'preview', 'side-by-side', 'fullscreen', '|',
                'guide'
            ],
        });

        // On form submit, copy EasyMDE value back to textarea
        var form = document.getElementById('note-form');
        if (form) {
            form.addEventListener('submit', function () {
                textarea.value = easyMDE.value();
            });
        }
    }
});
