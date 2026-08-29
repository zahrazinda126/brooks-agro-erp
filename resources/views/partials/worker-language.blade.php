<label class="worker-language" title="Choose language / Londa olulimi">
    <span aria-hidden="true">🌐</span>
    <select id="workerLanguage" aria-label="Choose language">
        <option value="en">English</option>
        <option value="lg">Luganda</option>
    </select>
</label>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var picker = document.getElementById('workerLanguage');
    if (!picker) return;

    var luganda = {
        'My Work':'Emirimu Gyange', 'Daily operations':'Emirimu gya buli lunaku',
        'Good morning':'Wasuze otya', 'Good afternoon':'Osiibye otya', 'Good evening':'Osiibye otya',
        'Poultry':'Enkoko', 'Goats':'Embuzzi', 'Rabbits':'Obumyu', 'Dogs':'Embwa',
        'Kituntu Garden':'Ennimiro ya Kituntu', 'Garden':'Ennimiro', 'Eggs':'Amagi',
        'Deaths':'Enkoko ezifudde', 'Feed mixing':'Okutabula emmere', 'Litter':'Kasasiro w’enkoko',
        '🥚 Eggs':'🥚 Amagi', '⚠️ Deaths':'⚠️ Enkoko ezifudde',
        '🌾 Feed mixing':'🌾 Okutabula emmere', '🧹 Litter':'🧹 Kasasiro w’enkoko',
        '🌾 Daily Feed':'🌾 Emmere ya buli lunaku', '💉 Vaccination':'💉 Okugema',
        '🩺 Daily Health & Treatment':'🩺 Obulamu n’obujjanjabi bwa buli lunaku',
        '⚠️ Daily Mortality':'⚠️ Ebyafudde leero', '🐣 Daily Production & Births':'🐣 Ebifulumiziddwa n’ebizaaliddwa leero',
        'Daily Feed':'Emmere ya buli lunaku', 'Vaccination':'Okugema',
        'Daily Health & Treatment':'Obulamu n’obujjanjabi bwa buli lunaku',
        'Daily Mortality':'Ebyafudde leero', 'Daily Production & Births':'Ebifulumiziddwa n’ebizaaliddwa leero',
        'Tap to enter':'Koona wano okuwandiika', 'Tap to enter today':'Koona wano okuwandiika ebya leero',
        'Done':'Kiwedde', 'Recorded today':'Kiwandiikiddwa leero', 'Save':'Tereka',
        'Save this feeding':'Tereka emmere eno', 'Back':'Ddayo', 'Go back':'Ddayo',
        'Choose house':'Londa ennyumba', 'Choose one of your assigned houses':'Londa emu ku nnyumba zo',
        'House':'Ennyumba', 'Birds':'Enkoko', 'birds':'enkoko', 'Morning':'Ku makya',
        'Afternoon':'Olweggulo', 'Evening':'Akawungeezi', 'Collection period':'Ekiseera ky’okukungula',
        'Trays':'Tuleyi', 'Loose eggs':'Amagi agatakola tuleyi', 'Any damaged eggs?':'Waliwo amagi agayonoonese?',
        'House daily measurements':'Ebipimo by’ennyumba ebya buli lunaku',
        'Egg mass (g)':'Obuzito bw’amagi (g)', 'Bird weight (kg)':'Obuzito bw’enkoko (kg)',
        'Anything to tell the supervisor?':'Waliwo ky’oyagala okutegeeza omukulembeze?',
        'How many birds died today?':'Enkoko mmeka ezifudde leero?',
        'Did you kill any sick or weak birds?':'Waliwo enkoko endwadde oba ennafu ezittiddwa?',
        'What did you see?':'Kiki kye walabye?', 'Feed taken for mixing':'Emmere etwaliddwa okutabula',
        'Feed given to chickens':'Emmere eweebwa enkoko', 'Feeding time':'Essaawa y’okuliisa',
        'Final mixed feed given (kg)':'Emmere etabuddwa eweeddwa (kg)', 'Note':'Eky’okwongerako',
        'Recorded today':'Ebiwandiikiddwa leero', 'Which ingredients were taken for mixing?':'Birungo ki ebyatwaliddwa okutabula?',
        'Bags':'Ensawo', 'change':'kyusa', 'How heavy is one bag?':'Ensawo emu ezitowa ki?',
        'Another size':'Obuzito obulala', 'Weight used':'Obuzito obukozeseddwa', 'kilograms':'kirogulaamu',
        'Weighed loose, not in bags':'Kipimiddwa nga si mu nsawo', 'Anything to add?':'Waliwo eky’okwongerako?',
        'Date':'Olunaku', 'Time':'Essaawa', 'Quantity':'Obungi', 'Amount':'Omuwendo',
        'Name':'Erinnya', 'Remarks':'Ebigambo ebirala', 'Submit':'Weereza', 'Cancel':'Sazaamu'
    };

    function translatableNodes(root) {
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node.nodeValue.trim() || node.parentElement.closest('script,style,select,.worker-language')) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });
        var nodes = [], node;
        while ((node = walker.nextNode())) nodes.push(node);
        return nodes;
    }

    var nodes = translatableNodes(document.body);
    var phrases = Object.keys(luganda).sort(function (a, b) { return b.length - a.length; });
    nodes.forEach(function (node) { node.__english = node.nodeValue; });
    document.querySelectorAll('input[placeholder],textarea[placeholder]').forEach(function (field) {
        field.dataset.englishPlaceholder = field.placeholder;
    });

    function translate(language) {
        document.documentElement.lang = language;
        nodes.forEach(function (node) {
            var source = node.__english;
            if (language !== 'lg') {
                node.nodeValue = source;
                return;
            }
            node.nodeValue = phrases.reduce(function (translated, english) {
                return translated.includes(english) ? translated.split(english).join(luganda[english]) : translated;
            }, source);
        });
        document.querySelectorAll('[data-english-placeholder]').forEach(function (field) {
            field.placeholder = language === 'lg'
                ? phrases.reduce(function (translated, english) {
                    return translated.includes(english) ? translated.split(english).join(luganda[english]) : translated;
                }, field.dataset.englishPlaceholder)
                : field.dataset.englishPlaceholder;
        });
        localStorage.setItem('brooks.worker.language', language);
    }

    picker.value = localStorage.getItem('brooks.worker.language') || 'en';
    picker.addEventListener('change', function () { translate(picker.value); });
    translate(picker.value);
});
</script>
