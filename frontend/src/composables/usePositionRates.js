import { ref, computed } from "vue";

// Default position rates (construction industry standard)
const defaultPositionRates = {
  // Skilled Workers
  Carpenter: 550,
  Mason: 550,
  Plumber: 520,
  Electrician: 570,
  Welder: 560,
  Painter: 480,
  "Steel Worker": 550,
  "Heavy Equipment Operator": 650,
  // Semi-Skilled Workers
  "Construction Worker": 450,
  Helper: 420,
  Laborer: 400,
  Rigger: 480,
  Scaffolder: 480,
  // Technical/Supervisory
  Foreman: 750,
  "Site Engineer": 1200,
  "Project Engineer": 1500,
  "Safety Officer": 800,
  "Quality Control Inspector": 700,
  Surveyor: 650,
  // Support Staff
  "Warehouse Keeper": 450,
  Timekeeper: 450,
  "Security Guard": 400,
  Driver: 480,
};

// Load position rates from localStorage or use defaults
const loadPositionRates = () => {
  const stored = localStorage.getItem("positionRates");
  if (stored) {
    try {
      return JSON.parse(stored);
    } catch (e) {
      console.error("Error loading position rates:", e);
      return { ...defaultPositionRates };
    }
  }
  return { ...defaultPositionRates };
};

// Shared reactive position rates
const positionRates = ref(loadPositionRates());

export function usePositionRates() {
  const positionOptions = computed(() => {
    return Object.keys(positionRates.value).sort();
  });

  const getRate = (position) => {
    return positionRates.value[position] || 450; // Default to 450 if position not found
  };

  const updateRate = (position, rate) => {
    positionRates.value[position] = rate;
    saveToStorage();
  };

  const addPosition = (position, rate) => {
    if (positionRates.value[position]) {
      throw new Error("Position already exists");
    }
    positionRates.value[position] = rate;
    saveToStorage();
  };

  const deletePosition = (position) => {
    delete positionRates.value[position];
    saveToStorage();
  };

  const getAllRates = () => {
    return { ...positionRates.value };
  };

  const resetToDefaults = () => {
    positionRates.value = { ...defaultPositionRates };
    saveToStorage();
  };

  const saveToStorage = () => {
    localStorage.setItem("positionRates", JSON.stringify(positionRates.value));
  };

  return {
    positionRates,
    positionOptions,
    getRate,
    updateRate,
    addPosition,
    deletePosition,
    getAllRates,
    resetToDefaults,
  };
}
