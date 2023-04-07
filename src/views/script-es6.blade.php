<script>
    let calendar;
    document.addEventListener('DOMContentLoaded', function() {
        let calendarEl = document.getElementById('calendar-{{ $id }}')
        calendar = new Calendar(calendarEl,
            {!! $options !!},
        );
        calendar.render();
    });
</script>
