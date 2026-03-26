(function () {
  const root = document.querySelector('[data-dogmatcher-root="true"]');
  if (!root || typeof SkilitsaDogMatcherData === 'undefined') {
    return;
  }

  const { questions, sections, uiTexts, settings, traits, mapping, breeds } = SkilitsaDogMatcherData;
  const storageKey = 'skilitsa_dogmatcher_state';

  const getUIText = (key, fallback) => {
    if (uiTexts && typeof uiTexts[key] !== 'undefined' && uiTexts[key] !== '') {
      return uiTexts[key];
    }
    return fallback;
  };

  const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

  const slugify = (value) =>
    value
      .toString()
      .toLowerCase()
      .trim()
      .replace(/\s+/g, '-')
      .replace(/[^a-z0-9\-]/g, '')
      .replace(/\-+/g, '-');

  const state = {
    currentIndex: 0,
    answers: {},
    dealbreakers: {},
    ignored: {},
  };

  const loadState = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (!raw) {
        return;
      }
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') {
        state.currentIndex = parsed.currentIndex || 0;
        state.answers = parsed.answers || {};
        state.dealbreakers = parsed.dealbreakers || {};
        state.ignored = parsed.ignored || {};
      }
    } catch (error) {
      // Ignore storage errors
    }
  };

  const saveState = () => {
    const payload = {
      currentIndex: state.currentIndex,
      answers: state.answers,
      dealbreakers: state.dealbreakers,
      ignored: state.ignored,
    };
    window.localStorage.setItem(storageKey, JSON.stringify(payload));
  };

  const clearState = () => {
    state.currentIndex = 0;
    state.answers = {};
    state.dealbreakers = {};
    state.ignored = {};
    window.localStorage.removeItem(storageKey);
  };

  const orderQuestions = () => {
    const ordered = [];
    const sectionOrder = Array.isArray(sections) ? sections.map((section) => section.section_key) : [];

    sectionOrder.forEach((sectionKey) => {
      questions
        .filter((question) => question.section_key === sectionKey)
        .forEach((question) => ordered.push(question));
    });

    questions
      .filter((question) => !question.section_key || !sectionOrder.includes(question.section_key))
      .forEach((question) => ordered.push(question));

    return ordered;
  };

  const orderedQuestions = orderQuestions();

  const findSectionLabel = (sectionKey) => {
    const match = Array.isArray(sections)
      ? sections.find((section) => section.section_key === sectionKey)
      : null;
    return match ? match.label : getUIText('section_title_fallback', 'DogMatcher');
  };

  const resolveImage = (question, keys) => {
    if (!question) {
      return '';
    }
    for (const key of keys) {
      if (typeof question[key] === 'string' && question[key] !== '') {
        return question[key];
      }
    }
    return '';
  };

  const getQuestionImages = (question) => {
    const first = resolveImage(question, ['image_1_url', 'image1_url', 'img_1_url', 'img_1_key']);
    const second = resolveImage(question, ['image_5_url', 'image5_url', 'img_5_url', 'img_5_key']);
    return {
      first,
      second,
      list: [first, second].filter(Boolean),
    };
  };

  const renderPulseImages = (images) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'skilitsa-dogmatcher__image-pulse';

    images.forEach((url, index) => {
      const img = document.createElement('img');
      img.src = url;
      img.alt = '';
      img.loading = 'lazy';
      img.className = 'skilitsa-dogmatcher__image-pulse-item';
      img.style.animationDelay = `${index * 1.5}s`;
      wrapper.appendChild(img);
    });

    return wrapper;
  };

  const renderSideBySideImages = (images) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'skilitsa-dogmatcher__image-fallback';
    images.forEach((url) => {
      const img = document.createElement('img');
      img.src = url;
      img.alt = '';
      img.loading = 'lazy';
      img.className = 'skilitsa-dogmatcher__image-fallback-item';
      wrapper.appendChild(img);
    });
    return wrapper;
  };

  const createSlider = (firstUrl, secondUrl) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'skilitsa-dogmatcher__image-slider';

    const baseImage = document.createElement('img');
    baseImage.src = firstUrl;
    baseImage.alt = '';
    baseImage.loading = 'lazy';
    baseImage.className = 'skilitsa-dogmatcher__image-slider-base';

    const overlay = document.createElement('div');
    overlay.className = 'skilitsa-dogmatcher__image-slider-overlay';

    const overlayImage = document.createElement('img');
    overlayImage.src = secondUrl;
    overlayImage.alt = '';
    overlayImage.loading = 'lazy';
    overlayImage.className = 'skilitsa-dogmatcher__image-slider-overlay-image';

    overlay.appendChild(overlayImage);

    const handle = document.createElement('div');
    handle.className = 'skilitsa-dogmatcher__image-slider-handle';

    const range = document.createElement('input');
    range.type = 'range';
    range.min = '0';
    range.max = '100';
    range.value = '50';
    range.className = 'skilitsa-dogmatcher__image-slider-range';
    range.setAttribute('aria-label', getUIText('slider_label', 'Compare images'));

    const updateSlider = (value) => {
      overlay.style.width = `${value}%`;
      handle.style.left = `${value}%`;
    };

    range.addEventListener('input', (event) => {
      updateSlider(event.target.value);
    });

    updateSlider(range.value);

    wrapper.appendChild(baseImage);
    wrapper.appendChild(overlay);
    wrapper.appendChild(handle);
    wrapper.appendChild(range);

    return { wrapper, range };
  };

  const renderQuestionImages = (question) => {
    const { first, second, list } = getQuestionImages(question);
    if (list.length === 0) {
      return null;
    }

    if (list.length === 1) {
      const wrapper = document.createElement('div');
      wrapper.className = 'skilitsa-dogmatcher__image-single';
      const img = document.createElement('img');
      img.src = list[0];
      img.alt = question.label || '';
      img.loading = 'lazy';
      img.className = 'skilitsa-dogmatcher__image-single-item';
      wrapper.appendChild(img);
      return wrapper;
    }

    const safeMode = settings && settings.image_mode_safe === 'pulse';
    if (safeMode) {
      return renderPulseImages(list);
    }

    try {
      const slider = createSlider(first, second);
      slider.range.addEventListener('touchstart', () => {
        slider.range.classList.add('is-active');
      });
      return slider.wrapper;
    } catch (error) {
      return renderSideBySideImages(list);
    }
  };

  const quizContainer = document.createElement('div');
  quizContainer.className = 'skilitsa-dogmatcher__quiz';

  const header = document.createElement('div');
  header.className = 'skilitsa-dogmatcher__header';

  const sectionTitle = document.createElement('h2');
  sectionTitle.className = 'skilitsa-dogmatcher__section-title';

  const progressWrapper = document.createElement('div');
  progressWrapper.className = 'skilitsa-dogmatcher__progress';

  const progressBar = document.createElement('div');
  progressBar.className = 'skilitsa-dogmatcher__progress-bar';

  const progressFill = document.createElement('div');
  progressFill.className = 'skilitsa-dogmatcher__progress-fill';
  progressBar.appendChild(progressFill);

  const progressText = document.createElement('span');
  progressText.className = 'skilitsa-dogmatcher__progress-text';

  progressWrapper.appendChild(progressBar);
  progressWrapper.appendChild(progressText);

  header.appendChild(sectionTitle);
  header.appendChild(progressWrapper);

  const questionContainer = document.createElement('div');
  questionContainer.className = 'skilitsa-dogmatcher__question-container';

  const controls = document.createElement('div');
  controls.className = 'skilitsa-dogmatcher__controls';

  const backButton = document.createElement('button');
  backButton.type = 'button';
  backButton.className = 'skilitsa-dogmatcher__button is-secondary';
  backButton.textContent = getUIText('back_button', 'Back');

  const nextButton = document.createElement('button');
  nextButton.type = 'button';
  nextButton.className = 'skilitsa-dogmatcher__button';
  nextButton.textContent = getUIText('next_button', 'Next');

  const startOverButton = document.createElement('button');
  startOverButton.type = 'button';
  startOverButton.className = 'skilitsa-dogmatcher__link';
  startOverButton.textContent = getUIText('start_over', 'Start over');

  controls.appendChild(backButton);
  controls.appendChild(nextButton);
  controls.appendChild(startOverButton);

  const resultsContainer = document.createElement('div');
  resultsContainer.className = 'skilitsa-dogmatcher__results';

  quizContainer.appendChild(header);
  quizContainer.appendChild(questionContainer);
  quizContainer.appendChild(controls);

  root.appendChild(quizContainer);
  root.appendChild(resultsContainer);

  const renderProgress = () => {
    const total = orderedQuestions.length;
    const current = Math.min(state.currentIndex + 1, total);
    const percent = total > 0 ? Math.round((current / total) * 100) : 0;
    progressFill.style.width = `${percent}%`;
    progressText.textContent = getUIText('progress_label', `Question ${current} of ${total}`);
  };

  const getTraitLabels = () => {
    const labels = {};
    if (Array.isArray(traits)) {
      traits.forEach((trait) => {
        if (trait && trait.trait_key) {
          labels[trait.trait_key] = trait.label || trait.trait_key;
        }
      });
    }
    return labels;
  };

  const computeUserTraits = () => {
    const contributions = {};
    const weights = {};

    orderedQuestions.forEach((question) => {
      const answer = state.answers[question.id];
      if (!answer || state.ignored[question.id]) {
        return;
      }

      const questionWeight = Number(question.weight || 1);
      const mappingRows = Array.isArray(mapping)
        ? mapping.filter(
            (row) =>
              row.question_key === question.id && String(row.answer_value) === String(answer)
          )
        : [];

      mappingRows.forEach((row) => {
        const traitKey = row.trait_key;
        if (!traitKey) {
          return;
        }
        const weightOverride = row.weight_override ? Number(row.weight_override) : 1;
        const contribution = Number(answer) * weightOverride * questionWeight;
        contributions[traitKey] = (contributions[traitKey] || 0) + contribution;
        weights[traitKey] = (weights[traitKey] || 0) + weightOverride * questionWeight;
      });
    });

    const scores = {};
    Object.keys(contributions).forEach((traitKey) => {
      const total = contributions[traitKey];
      const weight = weights[traitKey];
      if (!weight) {
        scores[traitKey] = null;
        return;
      }
      const average = total / weight;
      scores[traitKey] = clamp(average, 1, 5);
    });

    return scores;
  };

  const computeBreedScores = (userTraits) => {
    const traitLabels = getTraitLabels();
    const strictness = Number(settings?.scoring_strictness || 3);
    const dealbreakerStrength = Number(settings?.dealbreaker_strength || 4);
    const dealbreakerThreshold = Number(settings?.dealbreaker_diff_threshold || 2);

    return (breeds || []).map((breed) => {
      const traitScores = [];
      const traitDiffs = [];

      Object.keys(userTraits).forEach((traitKey) => {
        const userScore = userTraits[traitKey];
        if (userScore === null || typeof userScore === 'undefined') {
          return;
        }
        const breedValue = Number(breed[traitKey]);
        if (!breedValue) {
          return;
        }
        const diff = Math.abs(userScore - breedValue);
        const traitScore = clamp(100 - diff * 25, 0, 100);
        traitScores.push(traitScore);
        traitDiffs.push({ traitKey, diff, traitScore });
      });

      let score = traitScores.length
        ? Math.round(traitScores.reduce((sum, value) => sum + value, 0) / traitScores.length)
        : 0;

      traitDiffs.forEach((trait) => {
        const penalty = (strictness - 1) * Math.pow(trait.diff, 2) * 0.3;
        score -= penalty;
      });

      const dealbreakerHits = [];
      orderedQuestions.forEach((question) => {
        if (!state.dealbreakers[question.id] || state.ignored[question.id]) {
          return;
        }
        const answer = state.answers[question.id];
        if (!answer) {
          return;
        }
        const mappingRows = Array.isArray(mapping)
          ? mapping.filter(
              (row) =>
                row.question_key === question.id && String(row.answer_value) === String(answer)
            )
          : [];
        mappingRows.forEach((row) => {
          const traitKey = row.trait_key;
          if (!traitKey || !userTraits[traitKey]) {
            return;
          }
          const breedValue = Number(breed[traitKey]);
          if (!breedValue) {
            return;
          }
          const diff = Math.abs(Number(answer) - breedValue);
          if (diff >= dealbreakerThreshold) {
            score -= dealbreakerStrength * 5;
            dealbreakerHits.push({ traitKey, diff });
          }
        });
      });

      score = clamp(Math.round(score), 0, 100);

      const bestMatches = [...traitDiffs]
        .sort((a, b) => a.diff - b.diff)
        .slice(0, 2)
        .map((item) => ({
          traitKey: item.traitKey,
          label: traitLabels[item.traitKey] || item.traitKey,
          diff: item.diff,
        }));

      const biggestGaps = [...traitDiffs]
        .sort((a, b) => b.diff - a.diff)
        .slice(0, 2)
        .map((item) => ({
          traitKey: item.traitKey,
          label: traitLabels[item.traitKey] || item.traitKey,
          diff: item.diff,
        }));

      const dealbreakerMatches = [];
      orderedQuestions.forEach((question) => {
        if (!state.dealbreakers[question.id] || state.ignored[question.id]) {
          return;
        }
        const answer = state.answers[question.id];
        if (!answer) {
          return;
        }
        const mappingRows = Array.isArray(mapping)
          ? mapping.filter(
              (row) =>
                row.question_key === question.id && String(row.answer_value) === String(answer)
            )
          : [];
        mappingRows.forEach((row) => {
          const traitKey = row.trait_key;
          if (!traitKey) {
            return;
          }
          const breedValue = Number(breed[traitKey]);
          if (!breedValue) {
            return;
          }
          const diff = Math.abs(Number(answer) - breedValue);
          if (diff <= 1) {
            dealbreakerMatches.push({
              label: traitLabels[traitKey] || traitKey,
            });
          }
        });
      });

      return {
        breed,
        score,
        bestMatches,
        biggestGaps,
        dealbreakerMatches,
      };
    });
  };

  const buildBreedUrl = (breed) => {
    if (breed.external_url) {
      return breed.external_url;
    }
    const baseUrl = settings?.breed_base_url || '';
    const utm = settings?.breed_url_utm || '';
    const slugCandidate =
      breed.external_slug ||
      breed.english_name ||
      breed.label_en ||
      breed.name_en ||
      breed.label ||
      '';
    const slug = slugify(slugCandidate);
    if (!baseUrl || !slug) {
      return '';
    }
    return `${baseUrl}${slug}/${utm}`;
  };

  const renderResults = () => {
    quizContainer.classList.add('is-complete');
    questionContainer.innerHTML = '';
    header.style.display = 'none';
    controls.style.display = 'none';

    const userTraits = computeUserTraits();
    const scoredBreeds = computeBreedScores(userTraits).sort((a, b) => b.score - a.score);

    const topCardsCount = Number(settings?.results_top_cards || 3);
    const topShowCount = Number(settings?.results_top_show || 5);
    const moreCount = Number(settings?.results_more_count || 10);

    const topCards = scoredBreeds.slice(0, topCardsCount);
    const topList = scoredBreeds.slice(0, topShowCount);
    const moreList = scoredBreeds.slice(topShowCount, topShowCount + moreCount);

    resultsContainer.innerHTML = '';
    resultsContainer.classList.add('is-visible');

    const title = document.createElement('h3');
    title.textContent = getUIText('results_title', 'Τα αποτελέσματά σου');
    resultsContainer.appendChild(title);

    if (topCards.length) {
      const cardsWrapper = document.createElement('div');
      cardsWrapper.className = 'skilitsa-dogmatcher__results-cards';

      topCards.forEach((entry) => {
        const card = document.createElement('a');
        card.className = 'skilitsa-dogmatcher__result-card';
        card.href = buildBreedUrl(entry.breed) || '#';
        card.target = '_blank';
        card.rel = 'noopener';

        if (entry.breed.external_image_url) {
          const img = document.createElement('img');
          img.src = entry.breed.external_image_url;
          img.alt = entry.breed.label || '';
          img.loading = 'lazy';
          card.appendChild(img);
        }

        const badge = document.createElement('div');
        badge.className = 'skilitsa-dogmatcher__result-badge';
        badge.textContent = `${entry.score}%`;

        const name = document.createElement('h4');
        name.textContent = entry.breed.label || '';

      const snippet = document.createElement('p');
      const cardTraits = entry.bestMatches
        .map((trait) => trait.label)
        .slice(0, 2)
        .join(', ');
      snippet.textContent = `${getUIText('match_reason_label', 'Γιατί ταιριάζει')}: ${cardTraits || getUIText('match_reason_fallback', 'Ταιριάζει αρκετά καλά στα βασικά σου! 😊')}`;

        card.appendChild(badge);
        card.appendChild(name);
        card.appendChild(snippet);

        cardsWrapper.appendChild(card);
      });

      resultsContainer.appendChild(cardsWrapper);
    }

    const listWrapper = document.createElement('div');
    listWrapper.className = 'skilitsa-dogmatcher__results-list';

    const renderResultRow = (entry) => {
      const row = document.createElement('div');
      row.className = 'skilitsa-dogmatcher__result-row';

      const headerRow = document.createElement('div');
      headerRow.className = 'skilitsa-dogmatcher__result-row-header';

      const titleLink = document.createElement('a');
      titleLink.textContent = entry.breed.label || '';
      titleLink.href = buildBreedUrl(entry.breed) || '#';
      titleLink.target = '_blank';
      titleLink.rel = 'noopener';

      const badge = document.createElement('span');
      badge.className = 'skilitsa-dogmatcher__result-badge is-small';
      badge.textContent = `${entry.score}%`;

      headerRow.appendChild(titleLink);
      headerRow.appendChild(badge);
      row.appendChild(headerRow);

      const matchList = document.createElement('ul');
      matchList.className = 'skilitsa-dogmatcher__result-details';

      const matchItem = document.createElement('li');
      const dealbreakerMention = entry.dealbreakerMatches[0]
        ? `⭐ Ταιριάζει σε dealbreaker: ${entry.dealbreakerMatches[0].label}`
        : '';
      const bestTraits = entry.bestMatches
        .map((trait) => `✅ ${trait.label}`)
        .slice(0, 2)
        .join(', ');
      matchItem.textContent = `${getUIText('match_reason_label', 'Γιατί ταιριάζει')}: ${[dealbreakerMention, bestTraits]
        .filter(Boolean)
        .join(' • ') || getUIText('match_reason_fallback', 'Ταιριάζει αρκετά καλά στα βασικά σου! 😊')}`;
      matchList.appendChild(matchItem);

      const gapItem = document.createElement('li');
      const gaps = entry.biggestGaps
        .map((trait) => `⚠️ ${trait.label}`)
        .slice(0, 2)
        .join(', ');
      const trainingNote =
        Number(settings?.training_warning_strength || 4) >= 4
          ? getUIText('training_hint', 'Ίσως χρειαστεί λίγη επιπλέον εκπαίδευση εδώ 🐕‍🦺')
          : '';
      gapItem.textContent = `${getUIText('match_issues_label', 'Πιθανά θέματα')}: ${gaps || getUIText('no_major_gaps', 'Δεν υπάρχουν μεγάλα κενά.')}${
        trainingNote ? ` • ${trainingNote}` : ''
      }`;
      matchList.appendChild(gapItem);

      row.appendChild(matchList);
      return row;
    };

    topList.forEach((entry) => {
      listWrapper.appendChild(renderResultRow(entry));
    });

    resultsContainer.appendChild(listWrapper);

    if (moreList.length) {
      const moreWrapper = document.createElement('div');
      moreWrapper.className = 'skilitsa-dogmatcher__results-more';
      const moreButton = document.createElement('button');
      moreButton.type = 'button';
      moreButton.className = 'skilitsa-dogmatcher__button is-secondary';
      moreButton.textContent = getUIText('more_results', 'More results');

      const moreListContainer = document.createElement('div');
      moreListContainer.className = 'skilitsa-dogmatcher__results-more-list';
      moreListContainer.style.display = 'none';

      moreList.forEach((entry) => {
        moreListContainer.appendChild(renderResultRow(entry));
      });

      moreButton.addEventListener('click', () => {
        moreListContainer.style.display = 'block';
        moreButton.disabled = true;
      });

      moreWrapper.appendChild(moreButton);
      moreWrapper.appendChild(moreListContainer);
      resultsContainer.appendChild(moreWrapper);
    }

    const actions = document.createElement('div');
    actions.className = 'skilitsa-dogmatcher__results-actions';

    const retakeButton = document.createElement('button');
    retakeButton.type = 'button';
    retakeButton.className = 'skilitsa-dogmatcher__button';
    retakeButton.textContent = getUIText('retake_test', 'Retake test');
    retakeButton.addEventListener('click', () => {
      clearState();
      header.style.display = '';
      controls.style.display = '';
      quizContainer.classList.remove('is-complete');
      renderQuestion();
    });

    const officialLink = document.createElement('a');
    officialLink.className = 'skilitsa-dogmatcher__button is-secondary';
    officialLink.textContent = getUIText('official_test_link', 'Go to official test page');
    officialLink.href = settings?.official_test_url || '#';
    officialLink.target = '_blank';
    officialLink.rel = 'noopener';

    actions.appendChild(retakeButton);
    actions.appendChild(officialLink);
    resultsContainer.appendChild(actions);

    const disclaimer = document.createElement('p');
    disclaimer.className = 'skilitsa-dogmatcher__disclaimer';
    const verbosity = Number(settings?.feedback_verbosity || 4);
    const baseDisclaimer =
      'Κάθε σκύλος είναι μοναδικός 🐾 Οι τάσεις της φυλής είναι ένας μόνο παράγοντας.';
    const extraDisclaimer =
      verbosity >= 4
        ? 'Η εμπειρία, η ιστορία και η εκπαίδευση επηρεάζουν τον χαρακτήρα και υπάρχουν πάντα μικρές εκπλήξεις.'
        : '';
    disclaimer.textContent = `${baseDisclaimer}${extraDisclaimer ? ` ${extraDisclaimer}` : ''}`;
    resultsContainer.appendChild(disclaimer);
  };

  const renderQuestion = () => {
    const question = orderedQuestions[state.currentIndex];
    if (!question) {
      renderResults();
      return;
    }

    resultsContainer.innerHTML = '';
    resultsContainer.classList.remove('is-visible');

    questionContainer.innerHTML = '';
    sectionTitle.textContent = findSectionLabel(question.section_key);

    const title = document.createElement('h3');
    title.className = 'skilitsa-dogmatcher__question-title';
    title.textContent = question.label;
    questionContainer.appendChild(title);

    if (question.helperText) {
      const helper = document.createElement('p');
      helper.className = 'skilitsa-dogmatcher__helper-text';
      helper.textContent = question.helperText;
      questionContainer.appendChild(helper);
    }

    const imageBlock = renderQuestionImages(question);
    if (imageBlock) {
      questionContainer.appendChild(imageBlock);
    }

    const scale = document.createElement('div');
    scale.className = 'skilitsa-dogmatcher__scale';

    const answerValue = state.answers[question.id];
    const isIgnored = !!state.ignored[question.id];

    for (let value = 1; value <= 5; value += 1) {
      const label = document.createElement('label');
      label.className = 'skilitsa-dogmatcher__scale-option';

      const input = document.createElement('input');
      input.type = 'radio';
      input.name = `dogmatcher-${question.id}`;
      input.value = String(value);
      input.disabled = isIgnored;
      input.checked = answerValue === String(value);
      input.addEventListener('change', () => {
        state.answers[question.id] = String(value);
        saveState();
        updateNextButton();
      });

      const text = document.createElement('span');
      if (value === 1) {
        text.textContent = question.scaleLabels?.['1'] || '1';
      } else if (value === 5) {
        text.textContent = question.scaleLabels?.['5'] || '5';
      } else {
        text.textContent = String(value);
      }

      label.appendChild(input);
      label.appendChild(text);
      scale.appendChild(label);
    }

    questionContainer.appendChild(scale);

    const toggles = document.createElement('div');
    toggles.className = 'skilitsa-dogmatcher__toggles';

    const dealbreakerLabel = document.createElement('label');
    dealbreakerLabel.className = 'skilitsa-dogmatcher__toggle';

    const dealbreakerInput = document.createElement('input');
    dealbreakerInput.type = 'checkbox';
    dealbreakerInput.checked = !!state.dealbreakers[question.id];
    dealbreakerInput.addEventListener('change', () => {
      state.dealbreakers[question.id] = dealbreakerInput.checked;
      saveState();
    });

    const dealbreakerText = document.createElement('span');
    dealbreakerText.textContent = getUIText('dealbreaker_label', 'Dealbreaker');

    dealbreakerLabel.appendChild(dealbreakerInput);
    dealbreakerLabel.appendChild(dealbreakerText);

    const ignoreLabel = document.createElement('label');
    ignoreLabel.className = 'skilitsa-dogmatcher__toggle';

    const ignoreInput = document.createElement('input');
    ignoreInput.type = 'checkbox';
    ignoreInput.checked = !!state.ignored[question.id];
    ignoreInput.addEventListener('change', () => {
      state.ignored[question.id] = ignoreInput.checked;
      if (ignoreInput.checked) {
        delete state.answers[question.id];
      }
      saveState();
      renderQuestion();
    });

    const ignoreText = document.createElement('span');
    ignoreText.textContent = getUIText('ignore_label', 'Ignore this question');

    ignoreLabel.appendChild(ignoreInput);
    ignoreLabel.appendChild(ignoreText);

    toggles.appendChild(dealbreakerLabel);
    toggles.appendChild(ignoreLabel);
    questionContainer.appendChild(toggles);

    renderProgress();
    updateNextButton();
  };

  const updateNextButton = () => {
    const question = orderedQuestions[state.currentIndex];
    if (!question) {
      nextButton.disabled = true;
      return;
    }
    const hasAnswer = !!state.answers[question.id];
    const isIgnored = !!state.ignored[question.id];
    nextButton.disabled = !(hasAnswer || isIgnored);
  };

  backButton.addEventListener('click', () => {
    if (state.currentIndex > 0) {
      state.currentIndex -= 1;
      saveState();
      renderQuestion();
    }
  });

  nextButton.addEventListener('click', () => {
    if (state.currentIndex < orderedQuestions.length) {
      state.currentIndex += 1;
      saveState();
      renderQuestion();
    }
  });

  startOverButton.addEventListener('click', () => {
    clearState();
    header.style.display = '';
    controls.style.display = '';
    quizContainer.classList.remove('is-complete');
    renderQuestion();
  });

  loadState();
  renderQuestion();
})();
