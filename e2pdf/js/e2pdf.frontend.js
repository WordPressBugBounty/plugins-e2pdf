var e2pdf = {
    updateViewArea: function (pdfIframe, listener) {
        if (pdfIframe.hasClass('e2pdf-pages-loaded') && pdfIframe.hasClass('e2pdf-responsive')) {
            var pdfIframeContents = pdfIframe.contents();
            if (pdfIframe.hasClass('e2pdf-responsive-page')) {
                var viewerHeight = parseInt(pdfIframeContents.find('#viewer .page').first().outerHeight());
            } else {
                var viewerHeight = parseInt(pdfIframeContents.find('#viewer').outerHeight());
            }
            var viewerContainerTop = parseInt(pdfIframeContents.find('#viewerContainer').offset().top);
            pdfIframe.innerHeight(viewerHeight + viewerContainerTop + 2);

            if (!pdfIframe.hasClass('e2pdf-responsive-page')) {
                pdfIframeContents.find('#viewerContainer').scrollTop(0);
            }
        }

        if (listener == 'pagesloaded') {
            var pdfIframeContents = pdfIframe.contents();
            pdfIframeContents.find('#viewerContainer').scrollTop(0);
        }
    },
    viewSinglePageSwitch: function (pdfIframe, page) {
        if (pdfIframe.hasClass('e2pdf-single-page-mode') && pdfIframe.hasClass('e2pdf-responsive')) {
            var page = parseInt(page);
            if (page) {
                var pdfIframeContents = pdfIframe.contents();
                pdfIframeContents.find('.page').not('.page[data-page-number="' + page + '"]').css({'position': 'absolute', 'visibility': 'hidden', 'z-index': '-1'});
                pdfIframeContents.find('.page[data-page-number="' + page + '"]').css({'position': 'relative', 'visibility': '', 'z-index': ''});
            }
        }
    },
    viewerOnLoad: function (iframe) {
        var pdfIframe = jQuery(iframe);
        if (!pdfIframe.hasClass('e2pdf-preload')) {

            var pdfIframeContents = pdfIframe.contents();
            pdfIframe.addClass('e2pdf-view-loaded');
            pdfIframeContents.find('html').addClass('e2pdf-view-loaded');

            if (iframe.contentWindow && iframe.contentWindow.PDFViewerApplication) {

                var PDFViewerApplication = iframe.contentWindow.PDFViewerApplication;
                PDFViewerApplication.initializedPromise.then(function () {

                    PDFViewerApplication.eventBus.on("pagesloaded", function (event) {
                        pdfIframe.addClass('e2pdf-pages-loaded');
                        pdfIframeContents.find('html').addClass('e2pdf-pages-loaded');
                        e2pdf.viewSinglePageSwitch(pdfIframe, 1);
                        e2pdf.updateViewArea(pdfIframe, 'pagesloaded');
                    });

                    PDFViewerApplication.eventBus.on("pagechanging", function (event) {
                        if (event && event.pageNumber) {
                            e2pdf.viewSinglePageSwitch(pdfIframe, event.pageNumber);
                            e2pdf.updateViewArea(pdfIframe, 'pagechanging');
                        }
                    });

                    var title = document.title;
                    PDFViewerApplication.eventBus.on('beforeprint', function (event) {
                        if (PDFViewerApplication.printService) {
                            var pdfTitle;
                            var metadataTitle = PDFViewerApplication.metadata && PDFViewerApplication.metadata.get("dc:title");
                            if (metadataTitle) {
                                if (metadataTitle !== "Untitled" && !/[\uFFF0-\uFFFF]/g.test(metadataTitle)) {
                                    pdfTitle = metadataTitle;
                                }
                            }
                            if (pdfTitle) {
                                document.title = pdfTitle;
                            } else if (PDFViewerApplication.contentDispositionFilename) {
                                document.title = PDFViewerApplication.contentDispositionFilename;
                            }
                        }
                    });
                    PDFViewerApplication.eventBus.on('afterprint', function (event) {
                        document.title = title;
                    });

                    var listeners = [
                        'scalechanging',
                        'scalechanged',
                        'rotationchanging',
                        'updateviewarea',
                        'scrollmodechanged',
                        'spreadmodechanged',
                        'pagechanging',
                        'zoomin',
                        'zoomout',
                        'zoomreset',
                        'nextpage',
                        'previouspage'
                    ];
                    listeners.forEach(function (listener) {
                        PDFViewerApplication.eventBus.on(listener, function (event) {
                            e2pdf.updateViewArea(pdfIframe, listener);
                        });
                    });
                });
            } else {
                pdfIframeContents[0].addEventListener('pagesloaded', function (event) {
                    pdfIframe.addClass('e2pdf-pages-loaded');
                    pdfIframeContents.find('html').addClass('e2pdf-pages-loaded');
                    e2pdf.viewSinglePageSwitch(pdfIframe, 1);
                    e2pdf.updateViewArea(pdfIframe, 'pagesloaded');
                });

                pdfIframeContents[0].addEventListener('pagechanging', function (event) {
                    if (event && event.detail && event.detail.pageNumber) {
                        e2pdf.viewSinglePageSwitch(pdfIframe, event.detail.pageNumber);
                        e2pdf.updateViewArea(pdfIframe, 'pagechanging');
                    }
                });

                var listeners = [
                    'scalechanging',
                    'scalechanged',
                    'rotationchanging',
                    'updateviewarea',
                    'scrollmodechanged',
                    'spreadmodechanged',
                    'pagechanging',
                    'zoomin',
                    'zoomout',
                    'zoomreset',
                    'nextpage',
                    'previouspage'
                ];
                listeners.forEach(function (listener) {
                    pdfIframeContents[0].addEventListener(listener, function (event) {
                        e2pdf.updateViewArea(pdfIframe, listener);
                    });
                });
            }
        }
    },
    imageOnLoad: function (image) {
        var img = jQuery(image);
        var preload = img.attr('preload');
        if (preload) {
            img.removeClass('e2pdf-preload')
            img.removeAttr('preload');
            img.attr('src', preload);
        }
    }
};

jQuery(document).ready(function () {
    if (jQuery('.e2pdf-download.e2pdf-auto').not('.e2pdf-iframe-download').length > 0) {
        jQuery('.e2pdf-download.e2pdf-auto').not('.e2pdf-iframe-download').each(function () {
            if (jQuery(this).hasClass('e2pdf-inline')) {
                window.open(jQuery(this).attr('href'), '_blank');
            } else {
                location.href = jQuery(this).attr('href');
            }
        });
    }
    jQuery('.modal').on('show.bs.modal', function () {
        var modal = jQuery(this);
        modal.find('iframe.e2pdf-preload').each(function () {
            jQuery(this).removeClass('e2pdf-preload').attr('src', jQuery(this).attr('preload'));
        });
    });
    var wpcf = document.querySelector('.wpcf7');
    if (wpcf !== null) {
        wpcf.addEventListener('wpcf7mailsent', function (event) {
            var message = event.detail.apiResponse.message;
            if (message && (message.includes('e2pdf-view') || message.includes('e2pdf-download'))) {
                if (jQuery('.wpcf7-response-output').length > 0) {
                    if (window.MutationObserver) {
                        new MutationObserver((mutationsList, observer) => {
                            for (var mutation of mutationsList) {
                                observer.disconnect();
                                jQuery('.wpcf7-response-output').html(jQuery('.wpcf7-response-output').text());
                            }
                        }).observe(jQuery('.wpcf7-response-output')[0], {attributes: false, childList: true, characterData: false});
                    } else {
                        setTimeout(function () {
                            jQuery('.wpcf7-response-output').html(jQuery('.wpcf7-response-output').text());
                        }, 500);
                    }
                }
            }
        }, false);
    }
});