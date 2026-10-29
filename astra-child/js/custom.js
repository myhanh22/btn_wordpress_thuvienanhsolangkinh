document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const topButton = document.getElementById('backToTopBtn');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (topButton) {
        const updateTop = () => { topButton.hidden = window.scrollY < 400; };
        window.addEventListener('scroll', updateTop, { passive: true });
        updateTop();
        topButton.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reducedMotion ? 'instant' : 'smooth' }));
    }
    const topicSelect = document.getElementById('lk-photo-topic');
    const newTopicField = document.getElementById('lk-new-topic-field');
    const newTopicInput = document.getElementById('lk-new-topic');
    if (topicSelect && newTopicField && newTopicInput) {
        const updateTopicField = () => {
            const custom = topicSelect.value === 'other';
            newTopicField.hidden = !custom;
            newTopicInput.required = custom;
            newTopicInput.disabled = !custom;
        };
        topicSelect.addEventListener('change', () => {
            updateTopicField();
            if (!newTopicField.hidden) newTopicInput.focus();
        });
        updateTopicField();
    }
    const grid = document.getElementById('lk-grid');
    if (!grid) return;
    const cards = [...grid.querySelectorAll('.lk-card')];
    const filters = [...document.querySelectorAll('[data-topic]')];
    const search = document.getElementById('lk-search');
    const author = document.getElementById('lk-author');
    const empty = document.querySelector('.lk-empty');
    const end = document.querySelector('.lk-gallery-end');
    const count = document.getElementById('lk-count');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase();
    let topic = 'all';
    function applyFilters() {
        const query = normalize(search.value.trim());
        let visible = 0;
        cards.forEach(card => {
            const matches = (topic === 'all' || card.dataset.topics.split(' ').includes(topic)) &&
                (!author.value || card.dataset.author === author.value) &&
                normalize(card.dataset.title + ' ' + card.dataset.author).includes(query);
            card.hidden = !matches;
            card.classList.toggle('lk-card-tall', matches && visible % 3 === 1);
            if (matches) visible++;
        });
        count.textContent = String(visible);
        empty.hidden = visible !== 0;
        end.hidden = visible === 0;
        filters.forEach(button => {
            const active = button.dataset.topic === topic;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    }
    filters.forEach(button => button.addEventListener('click', () => { topic = button.dataset.topic; applyFilters(); }));
    search.addEventListener('input', applyFilters);
    author.addEventListener('change', applyFilters);
    document.getElementById('lk-reset').addEventListener('click', () => {
        topic = 'all'; search.value = ''; author.value = ''; applyFilters(); search.focus();
    });
    document.querySelectorAll('[data-album]').forEach(link => link.addEventListener('click', () => {
        topic = link.dataset.album; search.value = ''; author.value = ''; applyFilters();
    }));
    applyFilters();
    const dialog = document.querySelector('.lk-lightbox');
    const image = document.getElementById('lk-lightbox-img');
    const error = document.getElementById('lk-image-error');
    let selected = 0;
    let visibleCards = [];
    let opener = null;
    function renderImage() {
        const card = visibleCards[selected];
        error.hidden = true;
        image.alt = card.querySelector('img').alt;
        image.hidden = true;
        image.src = card.querySelector('[data-full]').dataset.full;
        document.getElementById('lk-lightbox-title').textContent = card.dataset.title;
        document.getElementById('lk-lightbox-author').textContent = card.dataset.author;
        document.getElementById('lk-lightbox-position').textContent = `${selected + 1} / ${visibleCards.length}`;
        document.getElementById('lk-prev').disabled = visibleCards.length < 2;
        document.getElementById('lk-next').disabled = visibleCards.length < 2;
    }
    image.addEventListener('load', () => { image.hidden = false; error.hidden = true; });
    image.addEventListener('error', () => { if (dialog.open) { image.hidden = true; error.hidden = false; } });
    function navigate(step) { selected = (selected + step + visibleCards.length) % visibleCards.length; renderImage(); }
    cards.forEach(card => card.querySelector('.lk-photo').addEventListener('click', event => {
        opener = event.currentTarget;
        visibleCards = cards.filter(item => !item.hidden);
        selected = visibleCards.indexOf(card);
        renderImage();
        dialog.showModal();
        document.body.classList.add('lk-dialog-open');
        document.getElementById('lk-close').focus();
    }));
    document.getElementById('lk-close').addEventListener('click', () => dialog.close());
    document.getElementById('lk-prev').addEventListener('click', () => navigate(-1));
    document.getElementById('lk-next').addEventListener('click', () => navigate(1));
    dialog.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault(); navigate(event.key === 'ArrowLeft' ? -1 : 1);
        }
    });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('lk-dialog-open');
        image.hidden = true;
        image.removeAttribute('src');
        if (opener) opener.focus();
    });
});
