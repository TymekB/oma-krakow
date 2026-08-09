UPDATE sylius_channel_pricing cp
JOIN sylius_product_variant v ON v.id = cp.product_variant_id
SET cp.price = 8900
WHERE v.code = 'OLEJEK_CIALO_BLP-variant-0';

UPDATE sylius_channel_pricing cp
JOIN sylius_product_variant v ON v.id = cp.product_variant_id
SET cp.price = 3900
WHERE v.code = 'OLEJEK_ETER_EUKALIPTUS-variant-0';

UPDATE sylius_channel_pricing cp
JOIN sylius_product_variant v ON v.id = cp.product_variant_id
SET cp.price = 18000
WHERE v.code = 'VOUCHER_MASAZ_60-variant-0';
