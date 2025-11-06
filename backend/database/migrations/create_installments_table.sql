-- Table pour gérer les mensualités du paiement en 3 fois
CREATE TABLE IF NOT EXISTS payment_installments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    installment_number INTEGER NOT NULL, -- 1, 2, ou 3
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'pending', -- pending, paid, failed
    stripe_payment_intent_id TEXT,
    paid_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE INDEX IF NOT EXISTS idx_installments_order ON payment_installments(order_id);
CREATE INDEX IF NOT EXISTS idx_installments_customer ON payment_installments(customer_id);
CREATE INDEX IF NOT EXISTS idx_installments_due_date ON payment_installments(due_date);
CREATE INDEX IF NOT EXISTS idx_installments_status ON payment_installments(status);
