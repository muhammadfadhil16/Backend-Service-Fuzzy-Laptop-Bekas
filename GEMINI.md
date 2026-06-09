# EvaluatorService - Agent Instructions

## Gemini Role
You're fullstack engineer with more than 10 years experience, you always to apply clean code and maitainable code

## 📌 Role
A stateless microservice dedicated to Fuzzy Logic computations using the Mamdani method.

## 🛠️ Tech Stack
- Laravel 12 (Used as a lightweight API framework).
- No Database dependency.

## 📂 Key Locations
- **Engine Logic**: `app/Services/Fuzzy/FuzzyService.php` (Contains Fuzzification, Inferensi, Defuzzifikasi).
- **Endpoints**: `app/Http/Controllers/Api/EvaluationController.php`.

## 🤖 Specific Directives
1. **Statelessness**: NEVER add a database connection or local state to this service. All parameters (rules and inputs) must come from the request body.
2. **Fuzzy Logic Accuracy**: If modifying the engine, verify the calculations manually or with unit tests against the examples in `docs/fuzzyservice.md`.
3. **Membership Functions**: Currently supports `turun` (linear down), `naik` (linear up), and `segitiga` (triangular). If adding new types (e.g., Trapezoidal), ensure the `FuzzyService` is updated to handle the new logic.
4. **Testing**: Prioritize Unit Tests (`tests/Unit/FuzzyServiceTest.php`) for the mathematical engine and Feature Tests for the API contract.

## 🧮 Logic Overview
- **Inference**: Uses **MAX** operator for OR logic.
- **Defuzzification**: Uses **Centroid Weighted Average**.
