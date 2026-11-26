-- 0014 wood prices: the furniture price per m2 comes from the material (material_prices), and each colour of the sample
-- admin can carry its own price that replaces it. Without one a colour keeps the price of its material, so nothing
-- changes for the existing colours until the admin sets a price.
ALTER TABLE sample_colors ADD COLUMN IF NOT EXISTS furniture_price_per_m2 DECIMAL(10,2) NULL
    CHECK (furniture_price_per_m2 IS NULL OR furniture_price_per_m2 >= 0);
