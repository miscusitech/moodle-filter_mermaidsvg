MOODLEDIR ?= ../moodle

link:
	@mkdir -p $(MOODLEDIR)/filter
	@ln -snf $(PWD) $(MOODLEDIR)
	@echo "Linked into $(MOODLEDIR)"

unlink:
	@rm -f $(MOODLEDIR)
	@echo "Unlinked from $(MOODLEDIR)"
