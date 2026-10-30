// ===== Dummy catalog data =====
// Product images: a stock photo from assets/images/products/ when one matches, otherwise
// assets/images/products/<slug>.jpg (falls back to the category icon until you add it).

const STOCK = name => `assets/images/products/stock-${name}.jpg`;

const IMG = {
  backpack: STOCK('backpack'),
  greenPack: STOCK('green-pack'),
  knives: STOCK('knives'),
  pistol: STOCK('pistol'),
  boots: STOCK('boots'),
  tent: STOCK('tent'),
  tentGreen: STOCK('tent-green'),
  tentField: STOCK('tent-field'),
  jacket: STOCK('jacket'),
  candle: STOCK('candle'),
  campfire: STOCK('campfire'),
  watch: STOCK('watch'),
  hammock: STOCK('hammock')
};

// Products per subcategory. Format of each item:
// [name, price, oldPrice (0 = none), rating, reviews, imageKey (or ''), isNew]
const catalog = [
  {
    name: 'Archery', short: 'Archery', icon: 'fa-bullseye',
    subs: {
      'Bow Sights': [
        ['Trophy Ridge Fix 5-Pin Bow Sight', 100.49, 0, 4.5, 431, '', 0],
        ['Trophy Ridge React H4 4-Pin Bow Sight', 109.99, 0, 4.8, 386, '', 1]
      ],
      'Arrow Rests': [],
      'Quivers': [],
      'Stabilizers': [
        ['Trophy Ridge Blitz Stabilizer 6"', 20.99, 0, 4.2, 484, '', 0]
      ],
      'Broadheads': [
        ['Rage Hypodermic NC Broadhead 100 gr 3-Pack', 49.99, 0, 4.8, 242, '', 0],
        ['Muzzy Trocar HB Hybrid 4-Blade Broadhead 100 gr 3-Pack', 39.99, 0, 4.7, 457, '', 0]
      ],
      'Broadhead Blades & Accessories': [],
      'Archery Tools': [
        ['Truglo Crossbow Bolt Puller', 7.29, 0, 4.7, 129, '', 0]
      ],
      'Dampeners & Silencers': [
        ['Ravin Crossbow Vibration Dampeners', 34.49, 0, 4.6, 168, '', 0]
      ],
      'Arrows': [
        ['Carbon Express PileDriver Crossbow Bolts 20" 6-Pack', 44.99, 0, 4.4, 462, '', 0],
        ['Ravin Premium Crossbow Bolts 400 gr 6-Pack', 119.99, 0, 4.7, 409, '', 1]
      ],
      'Nocks': [
        ['Nockturnal Universal Lighted Nocks Green', 29.99, 0, 4.7, 345, '', 0]
      ],
      'Bow Releases': [],
      'Arrow Vanes': []
    }
  },
  {
    name: 'Outdoor Gear', short: 'Outdoor Gear', icon: 'fa-person-hiking',
    subs: {
      'Bags & Packs': [
        ['Allen Back Country Bull Kit Game Bags', 75.61, 82.99, 4.2, 60, '', 0]
      ],
      'Bats & Impact Tools': [],
      'Fishing Accessories': [],
      'Storage Boxes & Outdoor Gear': [
        ['Adventure Medical Trauma Pak with QuikClot', 36.49, 0, 4.6, 488, '', 0],
        ['Adventure Medical Mountain Hiker First Aid Kit', 32.99, 0, 4.6, 32, '', 0]
      ],
      'Lighters': [],
      'Hand Warmers': [],
      'Fire Starters': [],
      'Lighter Accessories': [],
      'Candles': []
    }
  },
  {
    name: 'Knives & Multi-Tools', short: 'Knives & Tools', icon: 'fa-screwdriver-wrench',
    subs: {
      'Folding Knives': [
        ['Outdoor Edge RazorPro L Folding Knife 3.5"', 44.99, 0, 4.9, 83, '', 0],
        ['Outdoor Edge Razor XD3 Replaceable Blade Folding Knife', 59.99, 0, 4.3, 349, '', 1]
      ],
      'Fixed Blade Knives': [
        ['Outdoor Edge ZipBlade Field Dressing Knife 4"', 13.99, 14.99, 4.9, 91, '', 0]
      ],
      'Tactical Pens': [],
      'Multi-Tools': [],
      'Swords': [],
      'Axes & Hatchets': [],
      'Knife Accessories': [
        ['Outdoor Edge Wildpak 8-Piece Game Processing Kit', 49.99, 0, 4.2, 476, '', 0]
      ]
    }
  },
  {
    name: 'Hunting Accessories', short: 'Hunting', icon: 'fa-binoculars',
    subs: {
      'Boot & Gear Care': [],
      'Scent Control & Laundry Care': [
        ['Scent Thief Field Spray 24 oz', 29.95, 0, 4.4, 206, '', 0],
        ['Scent-A-Way MAX Detergent Fresh Earth 32 oz', 31.81, 0, 4.8, 362, '', 0],
        ['Wildlife Research Scent Killer Bar Soap', 13.95, 0, 4.8, 458, '', 0],
        ['Tink\'s PEAK30 Doe Estrus Fogger 5 oz', 22.02, 0, 4.3, 173, '', 0]
      ],
      'Blinds & Seating': [
        ['Rhino R102 2-Man Ground Blind', 109.95, 129.99, 4.3, 93, '', 0],
        ['Ameristep Doghouse Extreme Ground Blind', 102.95, 149.99, 4.3, 53, '', 0],
        ['Muddy Carnage 58x58 Die Cut Blind', 226.1, 0, 4.8, 50, '', 1],
        ['Knight & Hale Low Profile Blind Chair', 135.95, 0, 4.9, 163, '', 0],
        ['Millennium Steel Hang-On Treestand with Footrest', 157.95, 0, 4.2, 332, '', 0],
        ['Primos Double Bull Tri-Stool Truth Camo', 79.95, 0, 4.3, 461, '', 0],
        ['Allen Foam Seat Cushion 13 x 14"', 32.34, 0, 4.7, 377, '', 0]
      ],
      'Slings': [
        ['Ravin Crossbow Non-Slip Shoulder Sling', 45.99, 0, 4.8, 354, '', 0]
      ],
      'Game Calls': [
        ['Quaker Boy Reign Box Call', 25.95, 26.99, 4.2, 412, '', 0],
        ['Quaker Boy Kirby\'s Klassic Combo Turkey Call 3-Pack', 46.95, 59.99, 4.8, 74, '', 0],
        ['Primos Hensanity Pot Call', 47.95, 69.99, 4.3, 301, '', 0],
        ['Primos Elk Select Mouth Calls 4-Pack', 48.95, 69.99, 4.9, 131, '', 0],
        ['Primos Hoochie Mama Elk Call', 49.99, 0, 4.6, 32, '', 0],
        ['Flambeau Circe Three-in-One Predator Call', 22.95, 0, 4.8, 202, '', 1],
        ['Haydel\'s Magnum Honker Goose Call', 34.95, 0, 4.3, 125, '', 0],
        ['Knight & Hale Ol\' Yeller Box Turkey Call', 79.99, 0, 4.4, 422, '', 0]
      ],
      'Trail Cameras': [
        ['Spypoint Flex Plus Cellular Trail Camera', 129.99, 149.99, 4.3, 197, '', 0],
        ['Moultrie Edge Pro 2 Cellular Trail Camera', 119.99, 0, 4.9, 363, '', 1],
        ['Stealth Cam Revolver Pro 2.0 360 Cellular Camera', 149.99, 159.99, 4.3, 21, '', 0]
      ]
    }
  },
  {
    name: 'Optics & Sights', short: 'Optics', icon: 'fa-crosshairs',
    subs: {
      'Scope Mounts & Accessories': [
        ['Butler Creek Flip-Open Objective Scope Cover 30mm', 7.79, 0, 4.6, 424, '', 0]
      ],
      'Scopes': [
        ['Simmons Pro Target Air Rifle Scope 3-9x40', 89.49, 0, 4.4, 86, '', 0],
        ['Simmons Pro Target Air Rifle Scope 2-7x32', 69.99, 79.49, 4.2, 444, '', 0],
        ['BSA Air Rifle Scope 3-9x40 AO Mil-Dot', 80.49, 0, 4.6, 104, '', 1]
      ],
      'Red Dot Sights': [
        ['Burris FastFire 3 Red Dot Sight 3 MOA', 139.99, 239.99, 4.4, 126, '', 0],
        ['Truglo Tru-Brite Dual Color Open Red Dot Sight', 74.99, 83.99, 4.2, 68, '', 0]
      ],
      'Handgun Sights': [],
      'Optic Mounting Plates': []
    }
  },
  {
    name: 'Shooting Accessories', short: 'Shooting', icon: 'fa-box-archive',
    subs: {
      'Shooting Rests & Bags': [],
      'Chronographs & Wind Meters': [],
      'Bipods & Tripods': [],
      'Targets & Accessories': []
    }
  },
  {
    name: 'Gun Cases & Storage', short: 'Gun Cases', icon: 'fa-suitcase',
    subs: {
      'Gun Cases': [],
      'Gun Safe Accessories': [],
      'Ammo Cans & Boxes': [],
      'Bow & Arrow Cases': [
        ['MTM Arrow Plus Case 36 Arrows', 44.99, 0, 4.7, 57, '', 0],
        ['Plano Bowmax Crossbow Case', 119.99, 0, 4.3, 341, '', 0]
      ]
    }
  },
  {
    name: 'Airgun Ammunition & Accessories', short: 'Airgun Ammo', icon: 'fa-circle-dot',
    subs: {
      'Pellets & BBs': [],
      'Airgun Accessories': [],
      'CO2 Cartridges': []
    }
  },
  {
    name: 'Airguns & Air Rifles', short: 'Airguns', icon: 'fa-gun',
    subs: {
      'Air Rifles & Pistols': [],
      'Airsoft Guns & BBs': [],
      'Slingshots': []
    }
  },
  {
    name: 'Gunsmithing Tools', short: 'Gunsmithing', icon: 'fa-toolbox',
    subs: {
      'Gunsmithing Screwdrivers': [],
      'Gun Vises': [],
      'Gunsmithing Tools': []
    }
  },
  {
    name: 'Gun Care & Cleaning', short: 'Gun Care', icon: 'fa-spray-can',
    subs: {
      'Cleaning Kits & Supplies': [],
      'Gun Oils & Solvents': []
    }
  },
  {
    name: 'Reloading Equipment', short: 'Reloading', icon: 'fa-gears',
    subs: {
      'Reloading Equipment': [
        ['Forster Bench Rest Full Length Die Set .308 Win', 116.49, 0, 4.6, 336, '', 0],
        ['Lee Precision Quick Trim Rifle Die .223 Rem', 11.29, 13.49, 4.7, 473, '', 0]
      ]
    }
  }
];

const slugify = s => s.toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

// Multi-word brands in the catalog; anything else uses the first word of the name
const BRANDS = ['Trophy Ridge', 'Carbon Express', 'Outdoor Edge', 'Butler Creek', 'Quaker Boy', 'Knight & Hale',
  'Scent Thief', 'Scent-A-Way', 'Wildlife Research', 'Stealth Cam', 'Adventure Medical'];
const brandOf = name => (BRANDS.find(b => name.startsWith(b)) || name.split(' ')[0]).replace(/'s$/, '');

// Flatten into categories + products
const products = [];
const categories = catalog.map(cat => {
  const slug = slugify(cat.name);
  const subs = Object.entries(cat.subs).map(([subName, items]) => {
    items.forEach(([name, price, oldPrice, rating, reviews, imgKey, isNew]) => {
      const pSlug = slugify(name);
      products.push({
        id: products.length + 1,
        name, slug: pSlug, brand: brandOf(name), price, rating, reviews,
        oldPrice: oldPrice || null,
        tag: oldPrice ? 'Sale' : (isNew ? 'New' : ''),
        img: IMG[imgKey] || `assets/images/products/${pSlug}.jpg`,
        icon: cat.icon,
        category: slug, categoryName: cat.name,
        sub: slugify(subName), subName
      });
    });
    return { name: subName, slug: slugify(subName), count: items.length };
  });
  return {
    name: cat.name, short: cat.short, slug, icon: cat.icon, subs,
    img: `assets/images/cat-${slug}.png`,
    count: subs.reduce((n, s) => n + s.count, 0)
  };
});

const productById = id => products.find(p => p.id === id);
const productByName = name => products.find(p => p.name === name);
