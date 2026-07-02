let pdfDoc = null;
let pageNum = 1;
let pageRendering = false;
let pageNumPending = null;
let scale = 1.0;
let canvas = null;
let ctx = null;

function initPdfViewer(url) {
    canvas = document.getElementById('pdf-canvas');
    ctx = canvas.getContext('2d');

    // Set PDF.js worker
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    // Load PDF
    pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
        pdfDoc = pdfDoc_;
        document.getElementById('page-count').textContent = pdfDoc.numPages;

        // Initial page render
        renderPage(pageNum);
        hideLoading();

        // Enable/disable navigation buttons
        updateNavigationButtons();
    }).catch(function(error) {
        console.error('Error loading PDF:', error);
        document.getElementById('loading-message').innerHTML =
            '<div class="error">Failed to load PDF. <button onclick="switchPdfViewer(\'browser\')" class="pdf-btn">Try Browser Viewer</button></div>';
    });

    // Event listeners for controls
    document.getElementById('prev-page').addEventListener('click', onPrevPage);
    document.getElementById('next-page').addEventListener('click', onNextPage);
    document.getElementById('zoom-in').addEventListener('click', onZoomIn);
    document.getElementById('zoom-out').addEventListener('click', onZoomOut);
}

function renderPage(num) {
    pageRendering = true;

    pdfDoc.getPage(num).then(function(page) {
        const viewport = page.getViewport({scale: scale});
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };

        const renderTask = page.render(renderContext);

        renderTask.promise.then(function() {
            pageRendering = false;
            if (pageNumPending !== null) {
                renderPage(pageNumPending);
                pageNumPending = null;
            }
        });
    });

    document.getElementById('page-num').textContent = num;
}

function queueRenderPage(num) {
    if (pageRendering) {
        pageNumPending = num;
    } else {
        renderPage(num);
    }
}

function onPrevPage() {
    if (pageNum <= 1) return;
    pageNum--;
    queueRenderPage(pageNum);
    updateNavigationButtons();
}

function onNextPage() {
    if (pageNum >= pdfDoc.numPages) return;
    pageNum++;
    queueRenderPage(pageNum);
    updateNavigationButtons();
}

function onZoomIn() {
    scale += 0.25;
    document.getElementById('zoom-level').textContent = Math.round(scale * 100) + '%';
    queueRenderPage(pageNum);
}

function onZoomOut() {
    if (scale <= 0.25) return;
    scale -= 0.25;
    document.getElementById('zoom-level').textContent = Math.round(scale * 100) + '%';
    queueRenderPage(pageNum);
}

function updateNavigationButtons() {
    document.getElementById('prev-page').disabled = (pageNum <= 1);
    document.getElementById('next-page').disabled = (pageNum >= pdfDoc.numPages);
}

function switchPdfViewer(type) {
    const pdfjsViewer = document.getElementById('pdfjs-viewer');
    const browserViewer = document.getElementById('browser-viewer');
    const pdfjsTab = document.getElementById('pdfjs-tab');
    const browserTab = document.getElementById('browser-tab');

    // Remove active class from all tabs
    [pdfjsTab, browserTab].forEach(tab => {
        if (tab) tab.classList.remove('active');
    });

    if (type === 'pdfjs') {
        pdfjsViewer.style.display = 'flex';
        browserViewer.style.display = 'none';
        pdfjsTab.classList.add('active');
    } else if (type === 'browser') {
        pdfjsViewer.style.display = 'none';
        browserViewer.style.display = 'block';
        browserTab.classList.add('active');

        // Load browser viewer if not already loaded
        if (!browserViewer.src) {
            showLoading();
            browserViewer.src = pdfUrl;
        }
    }
}