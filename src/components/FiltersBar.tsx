const filters = ["Couleur", "Type", "Matériaux", "Finition", "Nouveauté"];

export function FiltersBar() {
  return (
    <section className="border-b border-gray-200 bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-4 sm:px-6">
        {filters.map((filter) => (
          <button
            key={filter}
            type="button"
            className="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
          >
            {filter}
          </button>
        ))}
      </div>
    </section>
  );
}
