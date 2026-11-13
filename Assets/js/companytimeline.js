Mautic.companyTimelineOnLoad = function (container, response) {
    Mautic.leadTimelineOnLoad(container, response);
};

mQuery(document).on('mautic:onPageLoad:after', function (event, container) {
    var timelineForm = mQuery(container).find('#timeline-filters[data-company-timeline]');
    if (!timelineForm.length) {
        return;
    }

    var searchTimer;
    var requestId = 0;

    timelineForm.on('change', function () {
        timelineForm.submit();
    }).on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            timelineForm.submit();
        }, 300);
    }).on('submit', function (e) {
        e.preventDefault();
        var currentId = ++requestId;
        Mautic.postForm(timelineForm, function (response) {
            if (currentId !== requestId) {
                return;
            }
            response.target = '#timeline-table';
            mQuery('#TimelineCount').html(response.timelineCount);
            Mautic.processPageContent(response);
        });
    });

    var loadTimeline = function () {
        Mautic.loadAndProcessPageContent('#timeline-table');
    };

    if (mQuery('#company-timeline-container').hasClass('active')) {
        loadTimeline();
    } else {
        mQuery('a[href="#company-timeline-container"]').one('shown.bs.tab', loadTimeline);
    }
});
