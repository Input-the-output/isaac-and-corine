-- =====================================================================
-- Migration: Add 50 missing parent guests + cleanup test entries
-- Run this in phpMyAdmin on the live database: itoprogress2_isaac_corine
-- =====================================================================

-- ─── Step 1: Remove test entries ─────────────────────────────────────
DELETE FROM guests WHERE tenant_id = 'isaac-and-corine' AND name_lower LIKE 'georges ito test%';

-- ─── Step 2: Add 50 missing parents (rows 23–72 from "Parents 1") ───
INSERT INTO guests (tenant_id, name, name_lower, plus_one, plus_one_name, rsvp_status) VALUES
('isaac-and-corine', 'Ghassan et Denise Tambe', 'ghassan et denise tambe', 0, NULL, 'pending'),
('isaac-and-corine', 'Johnny et Tanya Keyrouz', 'johnny et tanya keyrouz', 0, NULL, 'pending'),
('isaac-and-corine', 'Charles et Anita Mansour', 'charles et anita mansour', 0, NULL, 'pending'),
('isaac-and-corine', 'Tony et Josette Gharios', 'tony et josette gharios', 0, NULL, 'pending'),
('isaac-and-corine', 'Ibrahim Abou Dib', 'ibrahim abou dib', 0, NULL, 'pending'),
('isaac-and-corine', 'Pierre et Terry Nasr', 'pierre et terry nasr', 0, NULL, 'pending'),
('isaac-and-corine', 'Tony et Dina Nasr', 'tony et dina nasr', 0, NULL, 'pending'),
('isaac-and-corine', 'Tony et Kinda Merhej', 'tony et kinda merhej', 0, NULL, 'pending'),
('isaac-and-corine', 'Jacques et Bernadette Saadé', 'jacques et bernadette saadé', 0, NULL, 'pending'),
('isaac-and-corine', 'Jean-Claude et Corine Abou Chedid', 'jean-claude et corine abou chedid', 0, NULL, 'pending'),
('isaac-and-corine', 'Raymond et Claudine Daher', 'raymond et claudine daher', 0, NULL, 'pending'),
('isaac-and-corine', 'Fouad et Joelle Zmokhol', 'fouad et joelle zmokhol', 0, NULL, 'pending'),
('isaac-and-corine', 'Georges et May Hadwan', 'georges et may hadwan', 0, NULL, 'pending'),
('isaac-and-corine', 'Tony et Katia Sayssa', 'tony et katia sayssa', 0, NULL, 'pending'),
('isaac-and-corine', 'Johnny et Martine Sayssa', 'johnny et martine sayssa', 0, NULL, 'pending'),
('isaac-and-corine', 'Nadim et May Badawi', 'nadim et may badawi', 0, NULL, 'pending'),
('isaac-and-corine', 'Patricia El Am', 'patricia el am', 0, NULL, 'pending'),
('isaac-and-corine', 'Naji et Nadine Achkar', 'naji et nadine achkar', 0, NULL, 'pending'),
('isaac-and-corine', 'Micky et Monique Chebli', 'micky et monique chebli', 0, NULL, 'pending'),
('isaac-and-corine', 'Arz et Magda El Murr', 'arz et magda el murr', 0, NULL, 'pending'),
('isaac-and-corine', 'Ronald et Lina Moussa', 'ronald et lina moussa', 0, NULL, 'pending'),
('isaac-and-corine', 'Marwan et Nayla Saadé', 'marwan et nayla saadé', 0, NULL, 'pending'),
('isaac-and-corine', 'Samir et Christiane Tamari', 'samir et christiane tamari', 0, NULL, 'pending'),
('isaac-and-corine', 'Raghid et Nada Saba', 'raghid et nada saba', 0, NULL, 'pending'),
('isaac-and-corine', 'Rami et Nazek Mortada', 'rami et nazek mortada', 0, NULL, 'pending'),
('isaac-and-corine', 'Joseph et Ghada Semaan', 'joseph et ghada semaan', 0, NULL, 'pending'),
('isaac-and-corine', 'Tony et Eliane Tamer', 'tony et eliane tamer', 0, NULL, 'pending'),
('isaac-and-corine', 'Georges et Micheline Okais', 'georges et micheline okais', 0, NULL, 'pending'),
('isaac-and-corine', 'Elias et Nadia Stephan', 'elias et nadia stephan', 0, NULL, 'pending'),
('isaac-and-corine', 'Michelle Saddi', 'michelle saddi', 0, NULL, 'pending'),
('isaac-and-corine', 'Jean et Micheline Chalouhi', 'jean et micheline chalouhi', 0, NULL, 'pending'),
('isaac-and-corine', 'Père Charbel Batour', 'père charbel batour', 0, NULL, 'pending'),
('isaac-and-corine', 'Père Denie Meyer', 'père denie meyer', 0, NULL, 'pending'),
('isaac-and-corine', 'Ghassan et Amal Salem', 'ghassan et amal salem', 0, NULL, 'pending'),
('isaac-and-corine', 'Joseph et Fifi Ayoub', 'joseph et fifi ayoub', 0, NULL, 'pending'),
('isaac-and-corine', 'Walid Ghosn', 'walid ghosn', 0, NULL, 'pending'),
('isaac-and-corine', 'Gizele Hajjar', 'gizele hajjar', 0, NULL, 'pending'),
('isaac-and-corine', 'Joseph Maalouf', 'joseph maalouf', 0, NULL, 'pending'),
('isaac-and-corine', 'Abdo et Soha Skaf', 'abdo et soha skaf', 0, NULL, 'pending'),
('isaac-and-corine', 'Bashir et Carole Farhat', 'bashir et carole farhat', 0, NULL, 'pending'),
('isaac-and-corine', 'Jihad et Marie Achkar', 'jihad et marie achkar', 0, NULL, 'pending'),
('isaac-and-corine', 'Ghassan et Kennie Chidiac', 'ghassan et kennie chidiac', 0, NULL, 'pending'),
('isaac-and-corine', 'Nabil et Dina Bazerji', 'nabil et dina bazerji', 0, NULL, 'pending'),
('isaac-and-corine', 'Nawal Ghosn', 'nawal ghosn', 0, NULL, 'pending'),
('isaac-and-corine', 'Raymond et Ghada Sadaka', 'raymond et ghada sadaka', 0, NULL, 'pending'),
('isaac-and-corine', 'Ghazi et Joumana Ghosn', 'ghazi et joumana ghosn', 0, NULL, 'pending'),
('isaac-and-corine', 'Ghosn Ghosn', 'ghosn ghosn', 0, NULL, 'pending'),
('isaac-and-corine', 'Joseph et Hanne Ghosn', 'joseph et hanne ghosn', 0, NULL, 'pending'),
('isaac-and-corine', 'Moussa Freiha', 'moussa freiha', 0, NULL, 'pending'),
('isaac-and-corine', 'Dolly Debline', 'dolly debline', 0, NULL, 'pending');

-- ─── Result: 274 real guests (was 224 + 5 test = 229) ───────────────
-- Verify: SELECT COUNT(*) FROM guests WHERE tenant_id = 'isaac-and-corine';
-- Expected: 274
