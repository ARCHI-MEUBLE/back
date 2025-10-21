import Image from "next/image";

export type ProductTemplate = {
  id: string;
  name: string;
  price: number;
  image: string;
};

type ProductCardProps = {
  template: ProductTemplate;
};

export function ProductCard({ template }: ProductCardProps) {
  return (
    <article className="group flex flex-col space-y-4">
      <div className="overflow-hidden rounded-2xl bg-white shadow-sm transition-shadow group-hover:shadow-md">
        <div className="relative aspect-[4/3] w-full">
          <Image
            src={template.image}
            alt={template.name}
            fill
            className="object-cover"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, (max-width: 1536px) 25vw, 280px"
            loading="lazy"
          />
        </div>
      </div>
      <div className="flex flex-col space-y-2">
        <div>
          <h3 className="text-lg font-medium text-gray-900">{template.name}</h3>
          <p className="text-base font-semibold text-accent">{template.price.toLocaleString("fr-FR", { style: "currency", currency: "EUR" })}</p>
        </div>
        <button
          type="button"
          className="self-start rounded-full border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-800 transition hover:bg-gray-50"
        >
          Modifier ce modèle
        </button>
      </div>
    </article>
  );
}
