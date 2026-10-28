-- 0008 wider price range: the standard deviation shown to customers goes from about 6-7 % to about 10 % of the price.
UPDATE pricing_config SET param_value = CASE item_type
        WHEN 'workshop' THEN 0.15
        WHEN 'panels' THEN 0.15
        WHEN 'fronts' THEN 0.25
        WHEN 'hardware' THEN 0.20
        WHEN 'delivery' THEN 0.40
    END
WHERE category = 'uncertainty' AND param_name = 'relative'
  AND item_type IN ('workshop', 'panels', 'fronts', 'hardware', 'delivery');
