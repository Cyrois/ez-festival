export function filterArtistCheckIns(engagements, search = '', status = '') {
    const needle = search.trim().toLowerCase();

    return engagements.filter((engagement) => {
        const searchable = [
            engagement.name,
            engagement.contact?.name,
            engagement.contact?.email,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return (
            (!needle || searchable.includes(needle)) &&
            (!status || engagement.check_in_status === status)
        );
    });
}
