MOODLEDIR ?= ../moodle

link:
	@mkdir -p $(MOODLEDIR)/filter
	@ln -snf $(PWD) $(MOODLEDIR)/filter/mermaidsvg
	@echo "Linked into $(MOODLEDIR)/filter/mermaidsvg"

unlink:
	@rm -f $(MOODLEDIR)/filter/mermaidsvg
	@echo "Unlinked from $(MOODLEDIR)/filter/mermaidsvg"
